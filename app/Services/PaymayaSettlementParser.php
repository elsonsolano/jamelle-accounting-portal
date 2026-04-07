<?php

namespace App\Services;

class PaymayaSettlementParser
{
    private function decodeContent(string $raw): string
    {
        // UTF-16LE with BOM (0xFF 0xFE)
        if (str_starts_with($raw, "\xFF\xFE")) {
            return mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');
        }

        // UTF-16BE with BOM (0xFE 0xFF)
        if (str_starts_with($raw, "\xFE\xFF")) {
            return mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
        }

        // UTF-16LE without BOM (detect by null bytes pattern)
        if (strlen($raw) > 1 && $raw[1] === "\x00") {
            return mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
        }

        // Already UTF-8 or ASCII
        return $raw;
    }

    /**
     * Parse a PayMaya settlement XLS (HTML-disguised) file content.
     * Returns an array of ['bank_account' => '**1001', 'amount' => 71744.17, 'credit_date' => '2026-03-30']
     */
    public function parse(string $fileContent): array
    {
        $content = $this->decodeContent($fileContent);

        $dom = new \DOMDocument();
        @$dom->loadHTML($content);

        $table = $dom->getElementsByTagName('table')->item(0);

        if (!$table) {
            // Last resort: try raw content as UTF-8
            @$dom->loadHTML($fileContent);
            $table = $dom->getElementsByTagName('table')->item(0);
        }

        if (!$table) {
            return [];
        }

        $results = [];

        foreach ($table->getElementsByTagName('tr') as $idx => $row) {
            if ($idx === 0) continue; // skip header

            $cells = [];
            foreach ($row->getElementsByTagName('th') as $cell) {
                $cells[] = trim($cell->textContent);
            }
            foreach ($row->getElementsByTagName('td') as $cell) {
                $cells[] = trim($cell->textContent);
            }

            if (count($cells) < 13) continue;

            $clearingDate = $cells[1];  // e.g. 03/30/2026
            $bankAccount  = $cells[6];  // e.g. **1001
            $amountCredited = isset($cells[13]) ? $cells[13] : null;

            // Amount credited only appears on the first row of each bank account group (rowspan).
            // A bank account may have multiple groups in one email — capture all of them.
            if ($amountCredited && trim($amountCredited) !== '') {
                $results[] = [
                    'bank_account' => $bankAccount,
                    'amount'       => (float) str_replace(',', '', $amountCredited),
                    'credit_date'  => \Carbon\Carbon::createFromFormat('m/d/Y', $clearingDate)->format('Y-m-d'),
                ];
            }
        }

        return $results;
    }
}
