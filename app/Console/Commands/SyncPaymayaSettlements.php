<?php

namespace App\Console\Commands;

use App\Services\GmailService;
use App\Services\PaymayaSettlementParser;
use App\Services\PaymayaSyncService;
use Illuminate\Console\Command;

class SyncPaymayaSettlements extends Command
{
    protected $signature   = 'paymaya:sync';
    protected $description = 'Fetch PayMaya settlement emails from Gmail and post deposits to passbooks';

    public function handle(GmailService $gmail, PaymayaSettlementParser $parser, PaymayaSyncService $sync): int
    {
        $this->info('[' . now()->setTimezone('Asia/Manila')->format('Y-m-d H:i:s') . ' PHT] paymaya:sync started');
        $this->info('Fetching PayMaya settlement emails...');

        try {
            $emails = $gmail->fetchSettlementEmails();
        } catch (\Exception $e) {
            $this->error('Gmail error: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (empty($emails)) {
            $this->info('No new settlement emails found.');
            $this->info('Done.');
            return self::SUCCESS;
        }

        foreach ($sync->processEmails($emails, $parser) as $result) {
            $this->line("Processing: {$result['subject']}");

            match ($result['status']) {
                'duplicate' => $this->warn("  Duplicate detected — flagged for review."),
                'failed'    => $this->error("  {$result['message']}"),
                default     => $this->printLines($result['lines']),
            };
        }

        $this->info('Done.');
        return self::SUCCESS;
    }

    private function printLines(array $lines): void
    {
        foreach ($lines as $line) {
            if ($line['status'] === 'posted') {
                $this->info("  Posted ₱" . number_format($line['amount'], 2) . " → {$line['label']} ({$line['bank_account']})");
            } else {
                $this->warn("  No passbook matched for {$line['bank_account']}");
            }
        }
    }
}
