<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ExpenseEntry;
use App\Models\ExpensePeriod;
use App\Models\SalesEntry;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $now   = Carbon::now();
        $month = $now->month;
        $year  = $now->year;

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

        // Recent activity
        $recentExpenses = ExpenseEntry::with(['period.branch', 'category', 'creator'])
            ->latest()->take(8)->get();

        $recentSales = SalesEntry::with(['period.branch', 'creator'])
            ->latest()->take(5)->get();

        return view('dashboard', compact(
            'now', 'month', 'year',
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
