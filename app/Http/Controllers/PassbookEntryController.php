<?php

namespace App\Http\Controllers;

use App\Models\Passbook;
use App\Models\PassbookEntry;
use Illuminate\Http\Request;

class PassbookEntryController extends Controller
{
    public function create(Passbook $passbook)
    {
        $otherPassbooks = Passbook::with('branch')
            ->where('id', '!=', $passbook->id)
            ->orderBy('id')
            ->get();

        return view('passbook-entries.create', compact('passbook', 'otherPassbooks'));
    }

    public function store(Request $request, Passbook $passbook)
    {
        $data = $request->validate([
            'date'               => ['required', 'date'],
            'particulars'        => ['required', 'string', 'max:255'],
            'type'               => ['required', 'in:deposit,withdrawal,transfer_in,transfer_out,bank_charge,interest'],
            'amount'             => ['required', 'numeric', 'min:0.01'],
            'transfer_passbook_id' => ['nullable', 'exists:passbooks,id', 'required_if:type,transfer_out,transfer_in'],
        ]);

        $entry = PassbookEntry::create([
            'passbook_id' => $passbook->id,
            'date'        => $data['date'],
            'particulars' => $data['particulars'],
            'type'        => $data['type'],
            'amount'      => $data['amount'],
        ]);

        // Auto-create counter-entry for transfers
        if (in_array($data['type'], ['transfer_out', 'transfer_in']) && !empty($data['transfer_passbook_id'])) {
            $counterType = $data['type'] === 'transfer_out' ? 'transfer_in' : 'transfer_out';

            $counter = PassbookEntry::create([
                'passbook_id'     => $data['transfer_passbook_id'],
                'date'            => $data['date'],
                'particulars'     => $data['particulars'],
                'type'            => $counterType,
                'amount'          => $data['amount'],
                'linked_entry_id' => $entry->id,
            ]);

            $entry->update(['linked_entry_id' => $counter->id]);
        }

        return redirect()->route('passbooks.show', $passbook)
            ->with('success', 'Transaction recorded successfully.');
    }

    public function edit(PassbookEntry $passbookEntry)
    {
        $passbook       = $passbookEntry->passbook;
        $otherPassbooks = Passbook::with('branch')
            ->where('id', '!=', $passbook->id)
            ->orderBy('id')
            ->get();

        return view('passbook-entries.edit', compact('passbookEntry', 'passbook', 'otherPassbooks'));
    }

    public function update(Request $request, PassbookEntry $passbookEntry)
    {
        $data = $request->validate([
            'date'        => ['required', 'date'],
            'particulars' => ['required', 'string', 'max:255'],
            'amount'      => ['required', 'numeric', 'min:0.01'],
        ]);

        $passbookEntry->update($data);

        // Cascade to linked transfer entry
        if ($passbookEntry->linked_entry_id) {
            PassbookEntry::where('id', $passbookEntry->linked_entry_id)->update([
                'date'        => $data['date'],
                'particulars' => $data['particulars'],
                'amount'      => $data['amount'],
                'updated_by'  => auth()->id(),
            ]);
        }

        return redirect()->route('passbooks.show', $passbookEntry->passbook_id)
            ->with('success', 'Transaction updated successfully.');
    }

    public function destroy(PassbookEntry $passbookEntry)
    {
        $passbookId = $passbookEntry->passbook_id;

        // Delete linked transfer counter-entry first
        if ($passbookEntry->linked_entry_id) {
            PassbookEntry::where('id', $passbookEntry->linked_entry_id)->delete();
        }

        $passbookEntry->delete();

        return redirect()->route('passbooks.show', $passbookId)
            ->with('success', 'Transaction deleted successfully.');
    }
}
