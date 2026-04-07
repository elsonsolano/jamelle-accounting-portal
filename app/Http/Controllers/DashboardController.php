<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ExpenseEntry;
use App\Models\ExpensePeriod;
use App\Models\SalesEntry;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $now = Carbon::now();

        if ($request->has('month') && $request->has('year')) {
            $month = (int) $request->query('month');
            $year  = (int) $request->query('year');
            $month = max(1, min(12, $month));
            $year  = max(2020, min((int) $now->year, $year));
            session(['dashboard_month' => $month, 'dashboard_year' => $year]);
        } else {
            $month = (int) session('dashboard_month', $now->month);
            $year  = (int) session('dashboard_year',  $now->year);
        }

        $selectedDate = Carbon::createFromDate($year, $month, 1);

        $branches        = Branch::orderBy('name')->get();
        $revenueBranches = $branches->where('is_cost_center', false);
        $costCenters     = $branches->where('is_cost_center', true);

        // Current month periods with totals
        $currentPeriods = ExpensePeriod::where('month', $month)
            ->where('year', $year)
            ->with('branch')
            ->withSum('expenseEntries', 'amount')
            ->withSum('salesEntries', 'amount')
            ->get()
            ->sortBy('branch.name');

        $revenuePeriods   = $currentPeriods->filter(fn($p) => ! $p->branch->is_cost_center);
        $overheadPeriods  = $currentPeriods->filter(fn($p) => $p->branch->is_cost_center);

        // Month-level totals (revenue branches only)
        $totalSalesThisMonth      = $revenuePeriods->sum('sales_entries_sum_amount');
        $totalExpensesThisMonth   = $revenuePeriods->sum('expense_entries_sum_amount');
        $operatingIncomeThisMonth = $totalSalesThisMonth - $totalExpensesThisMonth;
        $periodsReporting         = $revenuePeriods->count();

        // Overhead expenses this month
        $overheadExpensesThisMonth = $overheadPeriods->sum('expense_entries_sum_amount');

        // All-time totals (revenue branches only)
        $revenueBranchIds = $revenueBranches->pluck('id');
        $allTimeSales     = SalesEntry::whereHas('period', fn($q) => $q->whereIn('branch_id', $revenueBranchIds))->sum('amount');
        $allTimeExpenses  = ExpenseEntry::whereHas('period', fn($q) => $q->whereIn('branch_id', $revenueBranchIds))->sum('amount');

        // Recent activity (always current — not affected by selected period)
        $recentExpenses = ExpenseEntry::with(['period.branch', 'category', 'creator'])
            ->latest()->take(8)->get();

        $recentSales = SalesEntry::with(['period.branch', 'creator'])
            ->latest()->take(5)->get();

        // Month options for the dropdown (last 18 months up to current)
        $monthOptions = collect();
        for ($i = 0; $i < 18; $i++) {
            $d = $now->copy()->startOfMonth()->subMonths($i);
            $monthOptions->push([
                'month' => (int) $d->month,
                'year'  => (int) $d->year,
                'label' => $d->format('F Y'),
            ]);
        }

        return view('dashboard', compact(
            'now', 'month', 'year', 'selectedDate', 'monthOptions',
            'branches', 'revenueBranches', 'costCenters',
            'currentPeriods', 'revenuePeriods', 'overheadPeriods',
            'totalSalesThisMonth', 'totalExpensesThisMonth',
            'operatingIncomeThisMonth', 'periodsReporting',
            'overheadExpensesThisMonth',
            'allTimeSales', 'allTimeExpenses',
            'recentExpenses', 'recentSales'
        ));
    }
}
