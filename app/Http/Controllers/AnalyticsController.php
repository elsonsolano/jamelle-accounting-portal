<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ExpenseEntry;
use App\Models\ExpensePeriod;
use App\Models\GrossSales;
use App\Models\Passbook;
use App\Models\SalesEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    private const OPERATIONAL_CATEGORIES = [
        'Staff Payroll and Allowance', 'Store Supplies', "BIR & City Gov't Fees",
        'Stocks Cost', 'Store Rental & CUSA', 'Pest Control', 'Hydro Lab',
        'Tel, Cable, Internet & Cel.', 'Fuel', 'Office Equipments', 'Logistics',
        'Commissary Rental & Electricity',
    ];

    private const OVERHEAD_CATEGORIES = [
        'Released 13th Month & SIL', 'Unreleased 13th Month', 'Store Maintenance',
        'Equipment Maintenance', 'SSS Employer Share', 'Pag-ibig Employer Share',
        'PHIC Employer Share', 'Representations', 'Other Expense',
        'Service Incentive Leave(SIL)', "Retainer's Fee", 'Royalty Fee', 'Ads Fee',
        'Ins., Renewals and Other Fees', 'Cashless Fees',
        'Unreleased Separation/Retirement Pay', 'Released Separation/Retirement Pay',
        'Miscellaneous', 'Loans Payable', 'Vehicle Maintenance',
    ];

    public function index(Request $request)
    {
        $now = Carbon::now();

        if ($request->has('month') && $request->has('year')) {
            $month = (int) $request->query('month');
            $year  = (int) $request->query('year');
            $month = max(1, min(12, $month));
            $year  = max(2020, min((int) $now->year, $year));
            session(['analytics_month' => $month, 'analytics_year' => $year]);
        } else {
            $month = (int) session('analytics_month', $now->month);
            $year  = (int) session('analytics_year',  $now->year);
        }

        $selectedDate = Carbon::createFromDate($year, $month, 1);
        $prevDate     = $selectedDate->copy()->subMonth();
        $prevMonth    = (int) $prevDate->month;
        $prevYear     = (int) $prevDate->year;

        $branches         = Branch::orderBy('name')->get();
        $revenueBranches  = $branches->where('is_cost_center', false)->values();
        $revenueBranchIds = $revenueBranches->pluck('id');

        // Month dropdown (last 18 months)
        $monthOptions = collect();
        for ($i = 0; $i < 18; $i++) {
            $d = $now->copy()->startOfMonth()->subMonths($i);
            $monthOptions->push([
                'month' => (int) $d->month,
                'year'  => (int) $d->year,
                'label' => $d->format('F Y'),
            ]);
        }

        // ── Chart 1: Net Operating Income by Branch ───────────────────────────
        $branchNoi = [];
        foreach ($revenueBranches as $branch) {
            $periodId = ExpensePeriod::where('branch_id', $branch->id)
                ->where('month', $month)->where('year', $year)
                ->value('id');
            $sales    = $periodId ? (float) SalesEntry::where('period_id', $periodId)->sum('amount')  : 0;
            $expenses = $periodId ? (float) ExpenseEntry::where('period_id', $periodId)->sum('amount') : 0;
            $vatItr   = $periodId ? (float) GrossSales::where('period_id', $periodId)->sum('vat_itr') : 0;
            $branchNoi[] = [
                'branch'   => $branch->name,
                'sales'    => $sales,
                'expenses' => $expenses,
                'noi'      => $sales - $expenses - $vatItr,
            ];
        }

        // ── Chart 2: 12-month Revenue vs Expenses trend ───────────────────────
        $trend = [];
        for ($i = 11; $i >= 0; $i--) {
            $d   = $now->copy()->startOfMonth()->subMonths($i);
            $m   = (int) $d->month;
            $y   = (int) $d->year;
            $ids = ExpensePeriod::whereIn('branch_id', $revenueBranchIds)
                ->where('month', $m)->where('year', $y)->pluck('id');
            $trend[] = [
                'label'    => $d->format('M Y'),
                'sales'    => (float) SalesEntry::whereIn('period_id', $ids)->sum('amount'),
                'expenses' => (float) ExpenseEntry::whereIn('period_id', $ids)->sum('amount'),
            ];
        }

        // ── Chart 3: Branch MoM Sales Comparison ─────────────────────────────
        $branchMom = [];
        foreach ($revenueBranches as $branch) {
            $curId  = ExpensePeriod::where('branch_id', $branch->id)
                ->where('month', $month)->where('year', $year)->value('id');
            $prevId = ExpensePeriod::where('branch_id', $branch->id)
                ->where('month', $prevMonth)->where('year', $prevYear)->value('id');
            $branchMom[] = [
                'branch'   => $branch->name,
                'current'  => $curId  ? (float) SalesEntry::where('period_id', $curId)->sum('amount')  : 0,
                'previous' => $prevId ? (float) SalesEntry::where('period_id', $prevId)->sum('amount') : 0,
            ];
        }

        // ── Chart 4: Top 10 Expense Categories ───────────────────────────────
        $allPeriodIds  = ExpensePeriod::where('month', $month)->where('year', $year)->pluck('id');
        $topCategories = ExpenseEntry::whereIn('period_id', $allPeriodIds)
            ->join('expense_categories', 'expense_entries.category_id', '=', 'expense_categories.id')
            ->selectRaw('expense_categories.name AS category_name, SUM(expense_entries.amount) AS total')
            ->groupBy('expense_categories.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn($r) => ['name' => $r->category_name, 'total' => (float) $r->total])
            ->values();

        // ── Chart 5: Operational vs Overhead Split by Branch ─────────────────
        $branchExpenseSplit = [];
        foreach ($revenueBranches as $branch) {
            $periodId    = ExpensePeriod::where('branch_id', $branch->id)
                ->where('month', $month)->where('year', $year)->value('id');
            $operational = 0;
            $overhead    = 0;
            if ($periodId) {
                $operational = (float) ExpenseEntry::where('period_id', $periodId)
                    ->whereHas('category', fn($q) => $q->whereIn('name', self::OPERATIONAL_CATEGORIES))
                    ->sum('amount');
                $overhead = (float) ExpenseEntry::where('period_id', $periodId)
                    ->whereHas('category', fn($q) => $q->whereIn('name', self::OVERHEAD_CATEGORIES))
                    ->sum('amount');
            }
            $branchExpenseSplit[] = [
                'branch'      => $branch->name,
                'operational' => $operational,
                'overhead'    => $overhead,
            ];
        }

        // ── Chart 6: Passbook Balances ────────────────────────────────────────
        $passbookBalances = Passbook::with('branch')->orderBy('bank_name')->get()
            ->map(function ($pb) {
                $credits = (float) $pb->entries()->whereIn('type', ['deposit', 'transfer_in', 'interest'])->sum('amount');
                $debits  = (float) $pb->entries()->whereIn('type', ['withdrawal', 'transfer_out', 'bank_charge'])->sum('amount');
                return [
                    'label'   => ($pb->branch?->name ?? '—') . ' — ' . $pb->bank_name,
                    'balance' => (float) $pb->opening_balance + $credits - $debits,
                ];
            })
            ->sortByDesc('balance')
            ->values();

        // ── Chart 7: Daily Sales (selected month, all revenue branches) ───────
        $revenuePeriodIds = ExpensePeriod::whereIn('branch_id', $revenueBranchIds)
            ->where('month', $month)->where('year', $year)->pluck('id');
        $dailySales = SalesEntry::whereIn('period_id', $revenuePeriodIds)
            ->selectRaw('date, SUM(amount) AS total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($r) => ['date' => Carbon::parse($r->date)->format('M d'), 'total' => (float) $r->total])
            ->values();

        return view('analytics.index', compact(
            'month', 'year', 'selectedDate', 'monthOptions',
            'prevDate',
            'branchNoi', 'trend', 'branchMom',
            'topCategories', 'branchExpenseSplit',
            'passbookBalances', 'dailySales',
        ));
    }
}
