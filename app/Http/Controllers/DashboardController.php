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

        // Current month periods with totals per branch
        $currentPeriods = ExpensePeriod::where('month', $month)
            ->where('year', $year)
            ->with('branch')
            ->withSum('expenseEntries', 'amount')
            ->withSum('salesEntries', 'amount')
            ->get()
            ->sortBy('branch.name');

        $branches = Branch::orderBy('name')->get();

        // Month-level totals
        $totalSalesThisMonth    = $currentPeriods->sum('sales_entries_sum_amount');
        $totalExpensesThisMonth = $currentPeriods->sum('expense_entries_sum_amount');
        $operatingIncomeThisMonth = $totalSalesThisMonth - $totalExpensesThisMonth;
        $periodsReporting = $currentPeriods->count();

        // All-time totals
        $allTimeSales    = SalesEntry::sum('amount');
        $allTimeExpenses = ExpenseEntry::sum('amount');

        // Recent expense entries across all periods
        $recentExpenses = ExpenseEntry::with(['period.branch', 'category', 'creator'])
            ->latest()
            ->take(8)
            ->get();

        // Recent sales entries
        $recentSales = SalesEntry::with(['period.branch', 'creator'])
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard', compact(
            'now', 'month', 'year',
            'currentPeriods', 'branches',
            'totalSalesThisMonth', 'totalExpensesThisMonth',
            'operatingIncomeThisMonth', 'periodsReporting',
            'allTimeSales', 'allTimeExpenses',
            'recentExpenses', 'recentSales'
        ));
    }
}
