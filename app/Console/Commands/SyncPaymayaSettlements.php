<?php

namespace App\Console\Commands;

use App\Models\Passbook;
use App\Models\PassbookEntry;
use App\Models\PaymayaImport;
use App\Models\PaymayaImportLine;
use App\Services\GmailService;
use App\Services\PaymayaSettlementParser;
use Illuminate\Console\Command;

class SyncPaymayaSettlements extends Command
{
    protected $signature   = 'paymaya:sync';
    protected $description = 'Fetch PayMaya settlement emails from Gmail and post deposits to passbooks';

    public function handle(GmailService $gmail, PaymayaSettlementParser $parser): int
    {
        $this->info('Fetching PayMaya settlement emails...');

        try {
            $emails = $gmail->fetchSettlementEmails();
        } catch (\Exception $e) {
            $this->error('Gmail error: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (empty($emails)) {
            $this->info('No new settlement emails found.');
            return self::SUCCESS;
        }

        foreach ($emails as $email) {
            $this->processEmail($email, $parser);
        }

        return self::SUCCESS;
    }

    private function processEmail(array $email, PaymayaSettlementParser $parser): void
    {
        $messageId = $email['message_id'];
        $subject   = $email['subject'];

        $this->line("Processing: {$subject}");

        // Duplicate check
        if (PaymayaImport::where('gmail_message_id', $messageId)->exists()) {
            $this->warn("  Duplicate detected — flagging for review.");

            PaymayaImport::create([
                'gmail_message_id' => $messageId . '_dup_' . time(),
                'subject'          => $subject,
                'credit_date'      => now()->toDateString(),
                'status'           => 'duplicate',
                'notes'            => "Duplicate of message ID: {$messageId}",
                'processed_at'     => now(),
            ]);

            return;
        }

        // Parse lines
        $lines = $parser->parse($email['attachment_content']);

        if (empty($lines)) {
            PaymayaImport::create([
                'gmail_message_id' => $messageId,
                'subject'          => $subject,
                'credit_date'      => now()->toDateString(),
                'status'           => 'failed',
                'notes'            => 'Could not parse attachment.',
                'processed_at'     => now(),
            ]);

            $this->error("  Failed to parse attachment.");
            return;
        }

        $creditDate = $lines[0]['credit_date'];

        $import = PaymayaImport::create([
            'gmail_message_id' => $messageId,
            'subject'          => $subject,
            'credit_date'      => $creditDate,
            'status'           => 'processed',
            'processed_at'     => now(),
        ]);

        foreach ($lines as $line) {
            $last4    = ltrim($line['bank_account'], '*');
            $passbook = Passbook::where('account_number', 'LIKE', "%{$last4}")->first();

            if (!$passbook) {
                PaymayaImportLine::create([
                    'import_id'    => $import->id,
                    'bank_account' => $line['bank_account'],
                    'amount'       => $line['amount'],
                    'credit_date'  => $line['credit_date'],
                    'status'       => 'unmatched',
                ]);

                $this->warn("  No passbook matched for {$line['bank_account']}");
                continue;
            }

            $entry = PassbookEntry::create([
                'passbook_id' => $passbook->id,
                'date'        => $line['credit_date'],
                'particulars' => 'PayMaya Settlement',
                'type'        => 'deposit',
                'amount'      => $line['amount'],
                'source'      => 'paymaya_auto',
            ]);

            PaymayaImportLine::create([
                'import_id'         => $import->id,
                'bank_account'      => $line['bank_account'],
                'amount'            => $line['amount'],
                'credit_date'       => $line['credit_date'],
                'passbook_id'       => $passbook->id,
                'passbook_entry_id' => $entry->id,
                'status'            => 'posted',
            ]);

            $this->info("  Posted ₱" . number_format($line['amount'], 2) . " → {$passbook->branch->name} ({$passbook->bank_name} {$line['bank_account']})");
        }
    }
}
