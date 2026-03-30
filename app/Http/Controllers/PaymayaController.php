<?php

namespace App\Http\Controllers;

use App\Models\PaymayaImport;
use App\Services\GmailService;
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

        $hasRefreshToken = !empty(config('services.google.refresh_token'));

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
                ->with('error', 'No refresh token returned. Please revoke access in your Google account and try again.');
        }

        // Write refresh token to .env
        $this->setEnvValue('GOOGLE_REFRESH_TOKEN', $token['refresh_token']);

        return redirect()->route('paymaya.index')
            ->with('success', 'Gmail connected successfully! The cron job will now sync PayMaya settlements automatically.');
    }

    public function syncNow()
    {
        Artisan::call('paymaya:sync');
        $output = Artisan::output();

        return redirect()->route('paymaya.index')
            ->with('success', 'Sync completed. ' . trim($output));
    }

    public function destroyImport(PaymayaImport $import)
    {
        // Also delete the linked passbook entries
        foreach ($import->lines as $line) {
            if ($line->passbook_entry_id) {
                \App\Models\PassbookEntry::where('id', $line->passbook_entry_id)->delete();
            }
        }

        $import->delete();

        return redirect()->route('paymaya.index')
            ->with('success', 'Import and its passbook entries deleted.');
    }

    private function setEnvValue(string $key, string $value): void
    {
        $envPath    = base_path('.env');
        $envContent = file_get_contents($envPath);

        if (str_contains($envContent, "{$key}=")) {
            $envContent = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $envContent);
        } else {
            $envContent .= "\n{$key}={$value}";
        }

        file_put_contents($envPath, $envContent);
    }
}
