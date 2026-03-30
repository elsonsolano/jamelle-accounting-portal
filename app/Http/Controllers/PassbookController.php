<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Passbook;
use Illuminate\Http\Request;

class PassbookController extends Controller
{
    public function index()
    {
        $branches = Branch::with(['passbooks'])->orderBy('name')->get();

        return view('passbooks.index', compact('branches'));
    }

    public function create()
    {
        $branches = Branch::orderBy('name')->get();

        return view('passbooks.create', compact('branches'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id'       => ['required', 'exists:branches,id'],
            'bank_name'       => ['required', 'string', 'max:255'],
            'account_number'  => ['nullable', 'string', 'max:255'],
            'account_name'    => ['nullable', 'string', 'max:255'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'opening_date'    => ['required', 'date'],
        ]);

        $passbook = Passbook::create($data);

        return redirect()->route('passbooks.show', $passbook)
            ->with('success', "Passbook \"{$passbook->label()}\" created successfully.");
    }

    public function show(Passbook $passbook)
    {
        $passbook->load(['branch', 'entries.creator', 'entries.updater', 'entries.linkedEntry.passbook.branch']);

        $otherPassbooks = Passbook::with('branch')
            ->where('id', '!=', $passbook->id)
            ->orderBy('id')
            ->get();

        // Compute running balance per entry
        $balance = (float) $passbook->opening_balance;
        $rows = [];
        foreach ($passbook->entries as $entry) {
            if ($entry->isCredit()) {
                $balance += (float) $entry->amount;
            } else {
                $balance -= (float) $entry->amount;
            }
            $rows[] = ['entry' => $entry, 'balance' => $balance];
        }

        return view('passbooks.show', compact('passbook', 'rows', 'otherPassbooks'));
    }
}
