<?php

namespace App\Services;

use App\Models\Passbook;
use App\Models\PassbookEntry;
use App\Models\PaymayaImport;
use App\Models\PaymayaImportLine;

class PaymayaSyncService
{
    /**
     * Process an array of fetched emails (from GmailService).
     * Returns an array of result messages for display.
     */
    public function processEmails(array $emails, PaymayaSettlementParser $parser): array
    {
        $results = [];

        foreach ($emails as $email) {
            $results[] = $this->processEmail($email, $parser);
        }

        return $results;
    }

    /**
     * Process a single email. Returns a result summary array.
     */
    public function processEmail(array $email, PaymayaSettlementParser $parser): array
    {
        $messageId = $email['message_id'];
        $subject   = $email['subject'];

        // Duplicate check
        if (PaymayaImport::where('gmail_message_id', $messageId)->exists()) {
            PaymayaImport::create([
                'gmail_message_id' => $messageId . '_dup_' . time(),
                'subject'          => $subject,
                'credit_date'      => now()->toDateString(),
                'status'           => 'duplicate',
                'notes'            => "Duplicate of message ID: {$messageId}",
                'processed_at'     => now(),
            ]);

            return [
                'subject' => $subject,
                'status'  => 'duplicate',
                'message' => 'Already processed — flagged as duplicate.',
                'lines'   => [],
            ];
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

            return [
                'subject' => $subject,
                'status'  => 'failed',
                'message' => 'Could not parse the XLS attachment.',
                'lines'   => [],
            ];
        }

        $creditDate = $lines[0]['credit_date'];

        $import = PaymayaImport::create([
            'gmail_message_id' => $messageId,
            'subject'          => $subject,
            'credit_date'      => $creditDate,
            'status'           => 'processed',
            'processed_at'     => now(),
        ]);

        $lineResults = [];

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

                $lineResults[] = [
                    'bank_account' => $line['bank_account'],
                    'amount'       => $line['amount'],
                    'status'       => 'unmatched',
                    'label'        => 'No passbook matched',
                ];

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

            $lineResults[] = [
                'bank_account' => $line['bank_account'],
                'amount'       => $line['amount'],
                'status'       => 'posted',
                'label'        => $passbook->branch->name . ' — ' . $passbook->bank_name,
            ];
        }

        return [
            'subject' => $subject,
            'status'  => 'processed',
            'message' => 'Processed successfully.',
            'lines'   => $lineResults,
        ];
    }
}
