<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\PassbookEntry;
use App\Models\PaymayaImport;
use App\Services\GmailService;
use App\Services\PaymayaSettlementParser;
use App\Services\PaymayaSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class PaymayaController extends Controller
{
    public function index()
    {
        $imports = PaymayaImport::with('lines.passbook.branch')
            ->orderByDesc('credit_date')
            ->orderByDesc('id')
            ->paginate(20);

        $hasRefreshToken = !empty(AppSetting::get('google_refresh_token', config('services.google.refresh_token')));

        return view('paymaya.index', compact('imports', 'hasRefreshToken'));
    }

    public function redirectToGoogle(GmailService $gmail)
    {
        return redirect($gmail->getAuthUrl());
    }

    public function handleGoogleCallback(Request $request, GmailService $gmail)
    {
        if (!$request->has('code')) {
            return redirect()->route('paymaya.index')->with('error', 'Google authorization failed.');
        }

        $token = $gmail->exchangeCode($request->get('code'));

        if (empty($token['refresh_token'])) {
            return redirect()->route('paymaya.index')
                ->with('error', 'No refresh token returned. Please revoke app access at myaccount.google.com/permissions and try again.');
        }

        AppSetting::set('google_refresh_token', $token['refresh_token']);

        return redirect()->route('paymaya.index')
            ->with('success', 'Gmail connected successfully! The refresh token has been saved.');
    }

    public function syncNow()
    {
        Artisan::call('paymaya:sync');
        $output = Artisan::output();

        return redirect()->route('paymaya.index')
            ->with('success', 'Sync completed. ' . trim($output));
    }

    public function searchAndSync(Request $request, GmailService $gmail, PaymayaSettlementParser $parser, PaymayaSyncService $sync)
    {
        $request->validate([
            'subject' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $subjectQuery = trim($request->input('subject'));

        try {
            $emails = $gmail->fetchSettlementEmailsBySubject($subjectQuery);
        } catch (\Exception $e) {
            return redirect()->route('paymaya.index')
                ->with('error', 'Gmail error: ' . $e->getMessage());
        }

        if (empty($emails)) {
            return redirect()->route('paymaya.index')
                ->with('error', "No emails found matching: \"{$subjectQuery}\"");
        }

        $results  = $sync->processEmails($emails, $parser);
        $summary  = collect($results)->groupBy('status');
        $processed = $summary->get('processed', collect())->count();
        $duplicate = $summary->get('duplicate', collect())->count();
        $failed    = $summary->get('failed', collect())->count();

        $parts = [];
        if ($processed) $parts[] = "{$processed} processed";
        if ($duplicate) $parts[] = "{$duplicate} duplicate(s)";
        if ($failed)    $parts[] = "{$failed} failed";

        $message = 'Search complete — ' . implode(', ', $parts) . '.';

        return redirect()->route('paymaya.index')
            ->with('success', $message);
    }

    public function destroyImport(PaymayaImport $import)
    {
        foreach ($import->lines as $line) {
            if ($line->passbook_entry_id) {
                PassbookEntry::where('id', $line->passbook_entry_id)->delete();
            }
        }

        $import->delete();

        return redirect()->route('paymaya.index')
            ->with('success', 'Import and its passbook entries deleted.');
    }
}
