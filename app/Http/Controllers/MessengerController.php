<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\DepositSlipSubmission;
use App\Models\MessengerStaff;
use App\Models\PassbookEntry;
use App\Services\DepositSlipParserService;
use App\Services\MessengerService;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MessengerController extends Controller
{
    public function __construct(
        private MessengerService $messenger,
        private DepositSlipParserService $parser,
    ) {}

    // ── Webhook verification (GET) ──────────────────────────────────────────
    public function verify(Request $request)
    {
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.messenger.verify_token')) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    // ── Incoming messages (POST) ────────────────────────────────────────────
    public function webhook(Request $request)
    {
        // Verify HMAC signature
        if (!$this->verifySignature($request)) {
            Log::warning('Messenger webhook: invalid signature');
            return response('Forbidden', 403);
        }

        $payload = $request->json()->all();

        if (($payload['object'] ?? '') !== 'page') {
            return response('OK', 200);
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                $this->handleEvent($event);
            }
        }

        return response('OK', 200);
    }

    private function handleEvent(array $event): void
    {
        $senderId = $event['sender']['id'] ?? null;

        if (!$senderId) {
            return;
        }

        // Ignore delivery/read receipts
        if (isset($event['delivery']) || isset($event['read'])) {
            return;
        }

        $message = $event['message'] ?? null;
        if (!$message) {
            return;
        }

        $staff = MessengerStaff::firstOrNew(['fb_sender_id' => $senderId]);

        if (!$staff->exists) {
            // Brand new sender — ask for employee code
            $staff->fb_name = $this->messenger->getSenderName($senderId);
            $staff->state   = 'pending_code';
            $staff->save();

            $this->messenger->sendText($senderId,
                "Hi {$staff->fb_name}! I'm the Jamelle Deposit Bot.\n\nPlease send your Employee Code to get started."
            );
            return;
        }

        if ($staff->state === 'pending_code') {
            $this->handlePendingCode($staff, $message);
        } else {
            $this->handleActiveStaff($staff, $message);
        }
    }

    private function handlePendingCode(MessengerStaff $staff, array $message): void
    {
        // If they sent an image before registering
        if (isset($message['attachments'])) {
            $this->messenger->sendText($staff->fb_sender_id,
                'Please send your Employee Code first before submitting deposit slips.'
            );
            return;
        }

        $code = trim($message['text'] ?? '');

        if (empty($code)) {
            $this->messenger->sendText($staff->fb_sender_id, 'Please enter your Employee Code.');
            return;
        }

        // Validate against external API
        $result = $this->validateEmployeeCode($code);

        if (!$result['valid']) {
            $this->messenger->sendText($staff->fb_sender_id,
                "Employee code \"{$code}\" was not found. Please check with HR and try again."
            );
            return;
        }

        // Register staff
        $staff->employee_code  = $code;
        $staff->branch_id      = $result['branch_id'] ?? null;
        $staff->state          = 'active';
        $staff->registered_at  = now();
        $staff->save();

        $staff->load('branch');
        $branchName = $staff->branch?->name ?? 'your branch';

        $this->messenger->sendText($staff->fb_sender_id,
            "You are now allowed to send the deposit slip! After you send the deposit slip at the \"Jamelle x LLAOLLAO {$branchName}\" Group Chat, please send the deposit slip to me also so that I can record it into our system. Thank you!"
        );

    }

    private function handleActiveStaff(MessengerStaff $staff, array $message): void
    {
        $attachments = $message['attachments'] ?? [];
        $imageUrl    = null;

        foreach ($attachments as $attachment) {
            if ($attachment['type'] === 'image') {
                $imageUrl = $attachment['payload']['url'] ?? null;
                break;
            }
        }

        if (!$imageUrl) {
            $this->messenger->sendText($staff->fb_sender_id,
                'Please send a photo of the deposit slip.'
            );
            return;
        }

        // Download image immediately (FB URLs expire)
        $imageContents = $this->messenger->downloadImage($imageUrl);

        if (!$imageContents) {
            $this->messenger->sendText($staff->fb_sender_id,
                'Sorry, we could not download the image. Please try sending it again.'
            );
            return;
        }

        // Process the deposit slip (async-like: reply first, then process)
        $this->messenger->sendText($staff->fb_sender_id,
            "Got it! Your deposit slip has been received. Our team will verify it shortly. Thank you!"
        );

        try {
            $submission = $this->parser->process($imageContents, $staff->fb_sender_id, $staff);

            // If low confidence, send a follow-up note
            if ($submission->parse_status === 'low_confidence') {
                $this->messenger->sendText($staff->fb_sender_id,
                    'Note: The image was a bit unclear. An admin will verify the details manually.'
                );
            }

            if ($submission->is_duplicate) {
                $this->messenger->sendText($staff->fb_sender_id,
                    'Note: This reference number appears to already be on record. An admin will review for duplicates.'
                );
            }
        } catch (\Throwable $e) {
            Log::error('Deposit slip processing failed', ['error' => $e->getMessage(), 'sender' => $staff->fb_sender_id]);
        }
    }

    private function validateEmployeeCode(string $code): array
    {
        $apiUrl = config('services.employee_api.url');

        if (!$apiUrl) {
            Log::warning('EMPLOYEE_API_URL not configured');
            return ['valid' => false];
        }

        try {
            $client   = new Client(['timeout' => 10]);
            $response = $client->get($apiUrl, [
                'query'       => ['code' => $code],
                'http_errors' => false,
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?? [];

            if (empty($data['exists'])) {
                return ['valid' => false];
            }

            return ['valid' => true, 'branch_id' => null];
        } catch (\Throwable $e) {
            Log::error('Employee API call failed', ['error' => $e->getMessage()]);
            return ['valid' => false];
        }
    }

    private function verifySignature(Request $request): bool
    {
        $appSecret = config('services.messenger.app_secret');
        $signature = $request->header('X-Hub-Signature-256');

        if (!$signature || !$appSecret) {
            // In development, skip verification if no secret configured
            return !$appSecret || app()->environment('local');
        }

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expected, $signature);
    }

    // ── Admin: update a submission (correct fields + manage passbook entry) ─
    public function update(Request $request, DepositSlipSubmission $submission)
    {
        $data = $request->validate([
            'bank_name'        => 'nullable|string|max:100',
            'account_number'   => 'nullable|string|max:50',
            'amount'           => 'nullable|numeric|min:0',
            'deposit_date'     => 'nullable|date',
            'reference_number' => 'nullable|string|max:100',
            'passbook_id'      => 'nullable|exists:passbooks,id',
        ]);

        // Strip special chars from account number
        if (!empty($data['account_number'])) {
            $data['account_number'] = preg_replace('/[^0-9]/', '', $data['account_number']);
        }

        $newPassbookId = $data['passbook_id'] ?? null;

        // If passbook changed or newly assigned, manage passbook entries
        if ($newPassbookId != $submission->passbook_id) {
            // Delete old passbook entry if it exists
            if ($submission->passbook_entry_id) {
                PassbookEntry::find($submission->passbook_entry_id)?->delete();
                $data['passbook_entry_id'] = null;
            }

            // Create new passbook entry if passbook selected and amount present
            $amount = $data['amount'] ?? $submission->amount;
            if ($newPassbookId && $amount) {
                $passbook    = \App\Models\Passbook::find($newPassbookId);
                $staffName   = $submission->staff?->fb_name ?? 'Messenger Bot';
                $branchName  = $submission->branch?->name ?? '';
                $entry       = new PassbookEntry([
                    'passbook_id' => $newPassbookId,
                    'date'        => $data['deposit_date'] ?? $submission->deposit_date?->toDateString() ?? now()->toDateString(),
                    'particulars' => "Deposit via Messenger — {$staffName}" . ($branchName ? " ({$branchName})" : ''),
                    'type'        => 'deposit',
                    'amount'      => $amount,
                    'source'      => 'messenger_bot',
                    'created_by'  => auth()->id(),
                    'updated_by'  => auth()->id(),
                ]);
                PassbookEntry::withoutEvents(fn() => $entry->save());
                $data['passbook_entry_id'] = $entry->id;
            }
        } elseif ($submission->passbook_entry_id) {
            // Same passbook — update the existing entry's amount and date
            $entry = PassbookEntry::find($submission->passbook_entry_id);
            if ($entry) {
                PassbookEntry::withoutEvents(function () use ($entry, $data, $submission) {
                    $entry->update([
                        'amount'     => $data['amount'] ?? $submission->amount,
                        'date'       => $data['deposit_date'] ?? $submission->deposit_date?->toDateString(),
                        'updated_by' => auth()->id(),
                    ]);
                });
            }
        }

        $submission->update(array_merge($data, [
            'admin_status' => 'approved',
            'reviewed_at'  => now(),
            'reviewed_by'  => auth()->id(),
        ]));

        return back()->with('success', 'Submission updated and approved.');
    }

    // ── Admin: reject a submission ───────────────────────────────────────────
    public function reject(DepositSlipSubmission $submission)
    {
        // Remove the passbook entry if one was created
        if ($submission->passbook_entry_id) {
            PassbookEntry::find($submission->passbook_entry_id)?->delete();
        }

        $submission->update([
            'admin_status'     => 'rejected',
            'passbook_entry_id' => null,
            'reviewed_at'      => now(),
            'reviewed_by'      => auth()->id(),
        ]);

        return back()->with('success', 'Submission rejected and passbook entry removed.');
    }

    // ── Utilities ───────────────────────────────────────────────────────────
    public function utils()
    {
        $staff = MessengerStaff::with('branch')->get();
        return view('messenger.utils', compact('staff'));
    }

    public function sendReminderNow()
    {
        $exitCode = Artisan::call('messenger:send-reminder');
        $output   = Artisan::output();

        if ($exitCode === 0) {
            return back()->with('success', 'Reminder sent! Output: ' . trim($output));
        }

        return back()->with('error', 'Command failed. Output: ' . trim($output));
    }

    // ── Admin: list all submissions ─────────────────────────────────────────
    public function submissions(Request $request)
    {
        $query = DepositSlipSubmission::with(['staff', 'branch', 'passbook', 'reviewer'])
            ->latest();

        if ($request->filled('status')) {
            if ($request->status === 'duplicate') {
                $query->where('is_duplicate', true);
            } elseif ($request->status === 'unreviewed') {
                $query->whereNull('reviewed_at');
            } else {
                $query->where('parse_status', $request->status)->where('is_duplicate', false);
            }
        }

        $submissions = $query->paginate(30)->withQueryString();
        $passbooks   = \App\Models\Passbook::with('branch')->orderBy('bank_name')->get();

        return view('deposit-slips.index', compact('submissions', 'passbooks'));
    }

    // ── Serve stored image (auth-protected) ─────────────────────────────────
    public function serveImage(DepositSlipSubmission $submission)
    {
        if (!$submission->image_path) {
            abort(404);
        }

        $disk = Storage::disk('r2');

        if (!$disk->exists($submission->image_path)) {
            abort(404);
        }

        $url = $disk->temporaryUrl($submission->image_path, now()->addMinutes(5));

        return redirect($url);
    }

    // ── Mark as reviewed ────────────────────────────────────────────────────
    public function markReviewed(DepositSlipSubmission $submission)
    {
        $submission->update([
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);

        return back()->with('success', 'Submission marked as reviewed.');
    }
}
