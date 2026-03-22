<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ExpensePeriod;
use App\Models\SalesEntry;

class SalesController extends Controller
{
    public function index()
    {
        $query = ExpensePeriod::with('branch')
            ->withSum('salesEntries', 'amount')
            ->latest('year')
            ->latest('month');

        if (request('branch_id')) {
            $query->where('branch_id', request('branch_id'));
        }
        if (request('month')) {
            $query->where('month', request('month'));
        }

        $periods  = $query->paginate(20)->withQueryString();
        $branches = Branch::orderBy('name')->get();

        return view('sales.index', compact('periods', 'branches'));
    }

    public function show(ExpensePeriod $period)
    {
        $period->load('branch');

        $entries = $period->salesEntries()
            ->with('creator', 'updater')
            ->get()
            ->map(fn($e) => [
                'id'              => $e->id,
                'date'            => $e->date->format('Y-m-d'),
                'amount'          => (float) $e->amount,
                'notes'           => $e->notes ?? '',
                'created_by_name' => $e->creator?->name,
                'updated_by_name' => $e->updater?->name,
            ])
            ->values()
            ->toArray();

        return view('sales.show', compact('period', 'entries'));
    }
}
