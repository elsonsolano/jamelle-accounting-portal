<?php

namespace App\Services;

class PaymayaSettlementParser
{
    /**
     * Parse a PayMaya settlement XLS (HTML-disguised) file content.
     * Returns an array of ['bank_account' => '**1001', 'amount' => 71744.17, 'credit_date' => '2026-03-30']
     */
    public function parse(string $fileContent): array
    {
        $content = mb_convert_encoding($fileContent, 'UTF-8', 'UTF-16LE');

        $dom = new \DOMDocument();
        @$dom->loadHTML($content);

        $table = $dom->getElementsByTagName('table')->item(0);

        if (!$table) {
            return [];
        }

        $results = [];
        $seenAccounts = [];

        foreach ($table->getElementsByTagName('tr') as $idx => $row) {
            if ($idx === 0) continue; // skip header

            $cells = [];
            foreach ($row->getElementsByTagName('td') as $cell) {
                $cells[] = trim($cell->textContent);
            }

            if (count($cells) < 13) continue;

            $clearingDate = $cells[1];  // e.g. 03/30/2026
            $bankAccount  = $cells[6];  // e.g. **1001
            $amountCredited = isset($cells[13]) ? $cells[13] : null;

            // Amount credited only appears on the first row of each bank account group
            if ($amountCredited && !isset($seenAccounts[$bankAccount])) {
                $seenAccounts[$bankAccount] = true;

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
