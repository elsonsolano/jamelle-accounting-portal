<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ExpenseCategory;
use App\Models\ExpensePeriod;
use App\Models\GrossSales;
use App\Models\SalesEntry;
use App\Http\Requests\StoreExpensePeriodRequest;
use App\Http\Requests\UpdateExpensePeriodRequest;

class ExpensePeriodController extends Controller
{
    public function index()
    {
        $query = ExpensePeriod::with('branch')
            ->withSum('expenseEntries', 'amount')
            ->withSum('salesEntries', 'amount')
            ->latest('year')->latest('month');

        if (request('branch_id')) {
            $query->where('branch_id', request('branch_id'));
        }
        if (request('month')) {
            $query->where('month', request('month'));
        }

        $periods  = $query->paginate(20)->withQueryString();
        $branches = Branch::orderBy('name')->get();

        return view('expense-periods.index', compact('periods', 'branches'));
    }

    public function create()
    {
        $branches = Branch::orderBy('name')->get();
        return view('expense-periods.create', compact('branches'));
    }

    public function store(StoreExpensePeriodRequest $request)
    {
        $period = ExpensePeriod::create($request->validated());

        return redirect()->route('expense-periods.show', $period)
            ->with('success', 'Period created.');
    }

    public function show(ExpensePeriod $expensePeriod)
    {
        $expensePeriod->load('branch');

        $monthPeriods = ExpensePeriod::where('branch_id', $expensePeriod->branch_id)
            ->where('year', $expensePeriod->year)
            ->orderBy('month')
            ->get();

        $entries = $expensePeriod->expenseEntries()
            ->with('category', 'creator', 'updater')
            ->get()
            ->map(fn($e) => [
                'id'               => $e->id,
                'date'             => $e->date->format('Y-m-d'),
                'category_id'      => $e->category_id,
                'category_name'    => $e->category->name,
                'particular'       => $e->particular,
                'amount'           => (float) $e->amount,
                'notes'            => $e->notes ?? '',
                'sort_order'       => $e->sort_order ?? 0,
                'created_by_name'  => $e->creator?->name,
                'updated_by_name'  => $e->updater?->name,
            ])
            ->values()
            ->toArray();

        $categories = ExpenseCategory::orderBy('name')->get(['id', 'name'])->toArray();

        $branches = Branch::orderBy('name')->get(['id', 'name'])->toArray();

        $grossSales = GrossSales::where('period_id', $expensePeriod->id)
            ->pluck('amount', 'branch_id')
            ->map(fn($a) => (float) $a)
            ->toArray();

        $salesEntries = $expensePeriod->salesEntries()
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

        $isCostCenter = $expensePeriod->branch->is_cost_center;

        return view('expense-periods.show', compact(
            'expensePeriod', 'monthPeriods', 'entries', 'categories', 'branches', 'grossSales', 'salesEntries',
            'isCostCenter'
        ));
    }

    public function edit(ExpensePeriod $expensePeriod)
    {
        $branches = Branch::orderBy('name')->get();
        return view('expense-periods.edit', compact('expensePeriod', 'branches'));
    }

    public function update(UpdateExpensePeriodRequest $request, ExpensePeriod $expensePeriod)
    {
        $expensePeriod->update($request->validated());

        return redirect()->route('expense-periods.show', $expensePeriod)
            ->with('success', 'Period updated.');
    }

    public function destroy(ExpensePeriod $expensePeriod)
    {
        $this->authorize('delete', $expensePeriod);
        $expensePeriod->delete();

        return redirect()->route('expense-periods.index')
            ->with('success', 'Period deleted.');
    }
}
