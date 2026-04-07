<?php

namespace App\Services;

use App\Models\DepositSlipSubmission;
use App\Models\MessengerStaff;
use App\Models\Passbook;
use App\Models\PassbookEntry;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DepositSlipParserService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function process(string $imageContents, string $fbSenderId, MessengerStaff $staff): DepositSlipSubmission
    {
        // Store image locally
        $imagePath = $this->storeImage($imageContents);

        // Call Claude Vision
        $parsed = $this->callClaudeVision($imageContents);

        // Determine parse status
        $parseStatus = $this->determineParseStatus($parsed);

        // Clean account number — strip dashes and special characters
        if (!empty($parsed['account_number'])) {
            $parsed['account_number'] = preg_replace('/[^0-9]/', '', $parsed['account_number']);
        }

        // Find matching passbook
        $passbook = null;
        if (!empty($parsed['account_number'])) {
            $passbook = $this->findPassbook($parsed['account_number']);
        }

        // Check for duplicate by reference number
        $isDuplicate = false;
        if (!empty($parsed['reference_number'])) {
            $isDuplicate = DepositSlipSubmission::where('reference_number', $parsed['reference_number'])
                ->where('is_duplicate', false)
                ->exists();
        }

        // Create passbook entry if matched and not a duplicate parse failure
        $passbookEntryId = null;
        if ($passbook && !$isDuplicate && $parseStatus !== 'failed' && !empty($parsed['amount'])) {
            $passbookEntryId = $this->createPassbookEntry($passbook, $parsed, $staff);
        }

        // Save submission record
        return DepositSlipSubmission::create([
            'fb_sender_id'       => $fbSenderId,
            'messenger_staff_id' => $staff->id,
            'branch_id'          => $staff->branch_id,
            'bank_name'          => $parsed['bank_name'] ?? null,
            'account_number'     => $parsed['account_number'] ?? null,
            'amount'             => isset($parsed['amount']) ? (float) $parsed['amount'] : null,
            'deposit_date'       => $parsed['deposit_date'] ?? null,
            'reference_number'   => $parsed['reference_number'] ?? null,
            'depositor_name'     => $parsed['depositor_name'] ?? null,
            'parse_status'       => $parseStatus,
            'confidence_notes'   => $parsed['confidence_notes'] ?? null,
            'is_duplicate'       => $isDuplicate,
            'passbook_id'        => $passbook?->id,
            'passbook_entry_id'  => $passbookEntryId,
            'image_path'         => $imagePath,
        ]);
    }

    private function storeImage(string $contents): ?string
    {
        try {
            $dir  = 'deposit-slips/' . now()->format('Y/m/d');
            $name = uniqid('slip_', true) . '.jpg';
            $path = $dir . '/' . $name;
            Storage::put($path, $contents);
            return $path;
        } catch (\Throwable $e) {
            Log::error('Failed to store deposit slip image', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function detectMimeType(string $contents): string
    {
        $bytes = substr($contents, 0, 4);
        if (str_starts_with($bytes, "\x89PNG")) return 'image/png';
        if (str_starts_with($bytes, "GIF8"))   return 'image/gif';
        if (str_starts_with($bytes, "\xFF\xD8")) return 'image/jpeg';
        return 'image/jpeg'; // default
    }

    private function callClaudeVision(string $imageContents): array
    {
        try {
            $apiKey   = config('services.anthropic.api_key');
            $base64   = base64_encode($imageContents);
            $mimeType = $this->detectMimeType($imageContents);

            if (!$apiKey) {
                Log::error('ANTHROPIC_API_KEY is not configured');
                return [];
            }

            $prompt = <<<'PROMPT'
You are analyzing a Philippine bank deposit slip. Extract the following fields:

1. bank_name: The bank name (e.g., "BDO", "BPI", "Metrobank", "UnionBank")
2. account_number: The account number the money was deposited INTO (not the depositor's account)
3. amount: Total amount deposited as a number only (no currency symbols, no commas). E.g. 42625.00
4. deposit_date: Date in YYYY-MM-DD format. Look for machine-stamped dates, not handwritten ones.
5. reference_number: The machine-generated transaction/reference number (not handwritten)
6. depositor_name: Name of the depositor or company
7. confidence: "high" if all fields are clearly readable, "medium" if some fields are unclear, "low" if the image is blurry or fields are mostly unreadable
8. confidence_notes: Brief note only if confidence is medium or low (e.g., "amount partially obscured", "image is blurry")

Return ONLY valid JSON with exactly these keys. Use null for any field you cannot read:
{"bank_name":null,"account_number":null,"amount":null,"deposit_date":null,"reference_number":null,"depositor_name":null,"confidence":"high","confidence_notes":null}
PROMPT;

            $response = $this->client->post('https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ],
                'json' => [
                    'model'      => 'claude-haiku-4-5-20251001',
                    'max_tokens' => 512,
                    'messages'   => [
                        [
                            'role'    => 'user',
                            'content' => [
                                [
                                    'type'   => 'image',
                                    'source' => [
                                        'type'       => 'base64',
                                        'media_type' => $mimeType,
                                        'data'       => $base64,
                                    ],
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $prompt,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            $body    = json_decode($response->getBody()->getContents(), true);
            $content = $body['content'][0]['text'] ?? '{}';

            // Extract JSON from response (Claude sometimes wraps it in backticks)
            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $parsed = json_decode($matches[0], true);
                if (is_array($parsed)) {
                    return $parsed;
                }
            }
        } catch (\Throwable $e) {
            Log::error('Claude Vision API call failed', [
                'error'   => $e->getMessage(),
                'api_key' => $apiKey ? 'set (length=' . strlen($apiKey) . ')' : 'MISSING',
            ]);
        }

        return [];
    }

    private function determineParseStatus(array $parsed): string
    {
        if (empty($parsed)) {
            return 'failed';
        }

        $confidence = $parsed['confidence'] ?? 'low';

        if ($confidence === 'low') {
            return 'low_confidence';
        }

        // Must have at least amount and reference_number to be a success
        if (empty($parsed['amount']) || empty($parsed['reference_number'])) {
            return 'low_confidence';
        }

        return 'success';
    }

    private function findPassbook(string $accountNumber): ?Passbook
    {
        $lastFour = substr($accountNumber, -4);

        if (strlen($lastFour) < 4) {
            return null;
        }

        return Passbook::where('account_number', 'like', '%' . $lastFour)->first();
    }

    private function createPassbookEntry(Passbook $passbook, array $parsed, MessengerStaff $staff): ?int
    {
        try {
            $staffName  = $staff->fb_name ?? 'Messenger Bot';
            $branchName = $staff->branch?->name ?? 'Unknown Branch';
            $particulars = "Deposit via Messenger — {$staffName} ({$branchName})";

            $entry = new PassbookEntry([
                'passbook_id' => $passbook->id,
                'date'        => $parsed['deposit_date'] ?? now()->toDateString(),
                'particulars' => $particulars,
                'type'        => 'deposit',
                'amount'      => $parsed['amount'],
                'source'      => 'messenger_bot',
                'created_by'  => null,
                'updated_by'  => null,
            ]);

            // Bypass boot() auth()->id() call since we're in webhook context
            PassbookEntry::withoutEvents(function () use ($entry) {
                $entry->save();
            });

            return $entry->id;
        } catch (\Throwable $e) {
            Log::error('Failed to create passbook entry from deposit slip', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
