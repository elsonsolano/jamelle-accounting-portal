<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\DepositSlipSubmission;
use App\Models\MessengerStaff;
use App\Services\DepositSlipParserService;
use App\Services\MessengerService;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

        $this->messenger->sendText($staff->fb_sender_id,
            "You're registered! You can now send deposit slip photos anytime."
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

        return view('deposit-slips.index', compact('submissions'));
    }

    // ── Serve stored image (auth-protected) ─────────────────────────────────
    public function serveImage(DepositSlipSubmission $submission)
    {
        if (!$submission->image_path || !Storage::exists($submission->image_path)) {
            abort(404);
        }

        return response(Storage::get($submission->image_path), 200, [
            'Content-Type'        => 'image/jpeg',
            'Content-Disposition' => 'inline',
        ]);
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
