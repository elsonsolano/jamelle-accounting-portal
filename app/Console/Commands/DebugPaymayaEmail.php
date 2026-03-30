<?php

namespace App\Console\Commands;

use App\Services\GmailService;
use Illuminate\Console\Command;

class DebugPaymayaEmail extends Command
{
    protected $signature   = 'paymaya:debug';
    protected $description = 'Debug PayMaya email MIME structure';

    public function handle(GmailService $gmail): int
    {
        $sender = config('services.google.paymaya_sender', 'noreply.settlement@maya.ph');
        $query  = "from:{$sender} subject:\"SETTLEMENT BREAKDOWN\" newer_than:2d";

        $listResponse = $gmail->apiGetPublic('https://gmail.googleapis.com/gmail/v1/users/me/messages', [
            'q'          => $query,
            'maxResults' => 1,
        ]);

        $messages = $listResponse['messages'] ?? [];

        if (empty($messages)) {
            $this->error('No emails found.');
            return self::FAILURE;
        }

        $messageId = $messages[0]['id'];
        $full      = $gmail->apiGetPublic("https://gmail.googleapis.com/gmail/v1/users/me/messages/{$messageId}", [
            'format' => 'full',
        ]);

        $this->info('Message ID: ' . $messageId);
        $this->info('MIME Type: ' . ($full['payload']['mimeType'] ?? 'unknown'));
        $this->line('');
        $this->info('Parts structure:');
        $this->dumpParts($full['payload']['parts'] ?? [], 0);

        // Try to download and inspect the attachment
        foreach ($full['payload']['parts'] ?? [] as $part) {
            $filename     = $part['filename'] ?? '';
            $attachmentId = $part['body']['attachmentId'] ?? null;

            if ($attachmentId && str_ends_with(strtoupper($filename), '.XLS')) {
                $this->line('');
                $this->info('Downloading attachment: ' . $filename);

                $att     = $gmail->apiGetPublic("https://gmail.googleapis.com/gmail/v1/users/me/messages/{$messageId}/attachments/{$attachmentId}");
                $data    = $att['data'] ?? '';
                $decoded = base64_decode(strtr($data, '-_', '+/'));

                $this->info('Decoded size: ' . strlen($decoded) . ' bytes');
                $this->info('First 4 bytes (hex): ' . bin2hex(substr($decoded, 0, 4)));
                $this->info('First 80 bytes (raw): ' . substr(preg_replace('/[^\x20-\x7E]/', '.', $decoded), 0, 80));

                // Run through parser
                $parser  = new \App\Services\PaymayaSettlementParser();
                $lines   = $parser->parse($decoded);
                $this->line('');
                $this->info('Parser result (' . count($lines) . ' lines):');
                foreach ($lines as $line) {
                    $this->line('  ' . json_encode($line));
                }

                // Also try parsing with debug
                $this->line('');
                $this->info('Manual parse test:');
                if (str_starts_with($decoded, "\xFF\xFE")) {
                    $utf8 = mb_convert_encoding(substr($decoded, 2), 'UTF-8', 'UTF-16LE');
                    $this->info('BOM: UTF-16LE, UTF-8 length: ' . strlen($utf8));
                    $dom = new \DOMDocument();
                    @$dom->loadHTML($utf8);
                    $tables = $dom->getElementsByTagName('table');
                    $this->info('Tables found: ' . $tables->length);
                    if ($tables->length > 0) {
                        $rows = $tables->item(0)->getElementsByTagName('tr');
                        $this->info('Rows found: ' . $rows->length);
                        // Show first data row cells
                        if ($rows->length > 1) {
                            $cells = [];
                            foreach ($rows->item(1)->getElementsByTagName('td') as $cell) {
                                $cells[] = trim($cell->textContent);
                            }
                            $this->info('Row 1 cells (' . count($cells) . '): ' . implode(' | ', array_slice($cells, 0, 8)));
                        }
                    }
                }
            }
        }

        return self::SUCCESS;
    }

    private function dumpParts(array $parts, int $depth): void
    {
        foreach ($parts as $i => $part) {
            $indent   = str_repeat('  ', $depth);
            $mime     = $part['mimeType'] ?? '?';
            $filename = $part['filename'] ?? '';
            $attachId = $part['body']['attachmentId'] ?? '';
            $size     = $part['body']['size'] ?? 0;

            $this->line("{$indent}[{$i}] mimeType={$mime} filename=\"{$filename}\" attachmentId={$attachId} size={$size}");

            if (!empty($part['parts'])) {
                $this->dumpParts($part['parts'], $depth + 1);
            }
        }
    }
}
