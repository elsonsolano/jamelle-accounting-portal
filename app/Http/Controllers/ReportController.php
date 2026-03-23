<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ExpenseCategory;
use App\Models\ExpenseEntry;
use App\Models\ExpensePeriod;
use App\Models\GrossSales;
use App\Services\ExpenseCalculatorService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private ExpenseCalculatorService $calculator) {}

    public function summary(ExpensePeriod $period)
    {
        $categoryTotals = $this->calculator->categoryTotals($period);
        $periodTotal    = $this->calculator->periodTotal($period);
        $grossSales     = $this->calculator->grossSalesTotal($period);
        $operating      = $this->calculator->operatingIncome($period);
        $net            = $this->calculator->netOperatingIncome($period);

        return view('reports.summary', compact(
            'period', 'categoryTotals', 'periodTotal', 'grossSales', 'operating', 'net'
        ));
    }

    public function operatingIncome(ExpensePeriod $period)
    {
        $data = [
            'grossSales'         => $this->calculator->grossSalesTotal($period),
            'totalExpense'       => $this->calculator->periodTotal($period),
            'operatingIncome'    => $this->calculator->operatingIncome($period),
            'vatItrEstimate'     => (float) $period->vat_itr_estimate,
            'netOperatingIncome' => $this->calculator->netOperatingIncome($period),
        ];

        return view('reports.operating-income', compact('period', 'data'));
    }

    public function consolidatedExpense()
    {
        $year     = request('year', now()->year);
        $month    = request('month', now()->month);
        $branches = Branch::orderBy('name')->get();

        $categories = ExpenseCategory::orderBy('name')->get();

        // Build matrix: category -> branch -> total
        $matrix = [];
        $branchTotals    = [];
        $categoryTotals  = [];
        $grandTotal      = 0;

        foreach ($categories as $cat) {
            $matrix[$cat->id] = [];
            $categoryTotals[$cat->id] = 0;
        }

        foreach ($branches as $branch) {
            $branchTotals[$branch->id] = 0;

            $period = ExpensePeriod::where('branch_id', $branch->id)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            foreach ($categories as $cat) {
                $amount = 0;
                if ($period) {
                    $amount = (float) $period->expenseEntries()
                        ->where('category_id', $cat->id)
                        ->sum('amount');
                }
                $matrix[$cat->id][$branch->id]  = $amount;
                $branchTotals[$branch->id]      += $amount;
                $categoryTotals[$cat->id]       += $amount;
                $grandTotal                     += $amount;
            }
        }

        return view('reports.consolidated', compact(
            'branches', 'categories', 'matrix', 'branchTotals', 'categoryTotals', 'grandTotal', 'year', 'month'
        ));
    }

    public function branchSummary(Request $request)
    {
        $fromMonth = (int) $request->input('from_month', now()->month);
        $fromYear  = (int) $request->input('from_year',  now()->year);
        $toMonth   = (int) $request->input('to_month',   now()->month);
        $toYear    = (int) $request->input('to_year',    now()->year);

        // Clamp so from <= to
        $fromKey = $fromYear * 100 + $fromMonth;
        $toKey   = $toYear   * 100 + $toMonth;
        if ($fromKey > $toKey) {
            [$fromMonth, $fromYear, $toMonth, $toYear] = [$toMonth, $toYear, $fromMonth, $fromYear];
            [$fromKey, $toKey] = [$toKey, $fromKey];
        }

        $isSingleMonth = ($fromKey === $toKey);
        $branches      = Branch::orderBy('name')->get();

        $rows = [];
        $grandSales = $grandExpenses = $grandVatItr = 0.0;

        foreach ($branches as $branch) {
            $periodIds = ExpensePeriod::where('branch_id', $branch->id)
                ->whereRaw('(year * 100 + month) BETWEEN ? AND ?', [$fromKey, $toKey])
                ->pluck('id');

            $totalSales    = (float) GrossSales::whereIn('period_id', $periodIds)->sum('amount');
            $totalExpenses = (float) ExpenseEntry::whereIn('period_id', $periodIds)->sum('amount');
            $vatItr        = (float) GrossSales::whereIn('period_id', $periodIds)->sum('vat_itr');

            $operatingIncome    = $totalSales - $totalExpenses;
            $netOperatingIncome = $operatingIncome - $vatItr;

            $singlePeriod = $isSingleMonth
                ? ExpensePeriod::where('branch_id', $branch->id)
                    ->where('month', $fromMonth)->where('year', $fromYear)->first()
                : null;

            $rows[] = [
                'branch'               => $branch,
                'is_cost_center'       => $branch->is_cost_center,
                'total_sales'          => $totalSales,
                'total_expenses'       => $totalExpenses,
                'operating_income'     => $operatingIncome,
                'vat_itr'              => $vatItr,
                'net_operating_income' => $netOperatingIncome,
                'period_id'            => $singlePeriod?->id,
            ];

            // Cost centers have no revenue — exclude from sales/income grand totals
            // but include their expenses so they reduce the overall operating income
            if (! $branch->is_cost_center) {
                $grandSales  += $totalSales;
                $grandVatItr += $vatItr;
            }
            $grandExpenses += $totalExpenses;
        }

        $grandOperating = $grandSales - $grandExpenses;
        $grandNet       = $grandOperating - $grandVatItr;

        return view('reports.branch-summary', compact(
            'rows', 'isSingleMonth',
            'fromMonth', 'fromYear', 'toMonth', 'toYear',
            'grandSales', 'grandExpenses', 'grandVatItr',
            'grandOperating', 'grandNet'
        ));
    }
}
