<?php

namespace App\Http\Controllers;

use App\Models\SalesEntry;
use Illuminate\Http\Request;

class SalesEntryController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'period_id' => ['required', 'integer', 'exists:expense_periods,id'],
            'date'      => ['required', 'date'],
            'amount'    => ['required', 'numeric', 'min:0'],
            'notes'     => ['nullable', 'string', 'max:500'],
        ]);

        $entry = SalesEntry::create($data);

        return response()->json($this->format($entry->load('creator', 'updater')), 201);
    }

    public function update(Request $request, SalesEntry $salesEntry)
    {
        $data = $request->validate([
            'date'   => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $salesEntry->update($data);

        return response()->json($this->format($salesEntry->load('creator', 'updater')));
    }

    public function destroy(SalesEntry $salesEntry)
    {
        $salesEntry->delete();

        return response()->json(['deleted' => true]);
    }

    private function format(SalesEntry $entry): array
    {
        return [
            'id'              => $entry->id,
            'date'            => $entry->date->format('Y-m-d'),
            'amount'          => (float) $entry->amount,
            'notes'           => $entry->notes ?? '',
            'created_by_name' => $entry->creator?->name,
            'updated_by_name' => $entry->updater?->name,
        ];
    }
}
