<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ExpenseCategory;
use App\Models\ExpenseEntry;
use App\Models\ExpensePeriod;
use App\Models\GrossSales;
use App\Models\SalesEntry;
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

        $operationalNames = [
            'Staff Payroll and Allowance', 'Store Supplies', "BIR & City Gov't Fees",
            'Stocks Cost', 'Store Rental & CUSA', 'Pest Control', 'Hydro Lab',
            'Tel, Cable, Internet & Cel.', 'Fuel', 'Office Equipments', 'Logistics',
            'Commissary Rental & Electricity',
        ];
        $overheadNames = [
            'Released 13th Month & SIL', 'Unreleased 13th Month', 'Store Maintenance',
            'Equipment Maintenance', 'SSS Employer Share', 'Pag-ibig Employer Share',
            'PHIC Employer Share', 'Representations', 'Other Expense',
            'Service Incentive Leave(SIL)', "Retainer's Fee", 'Royalty Fee', 'Ads Fee',
            'Ins., Renewals and Other Fees', 'Cashless Fees',
            'Unreleased Separation/Retirement Pay', 'Released Separation/Retirement Pay',
            'Miscellaneous', 'Loans Payable', 'Vehicle Maintenance',
        ];

        $operationalCats = $categories->filter(fn($c) => in_array($c->name, $operationalNames))->values();
        $overheadCats    = $categories->filter(fn($c) => in_array($c->name, $overheadNames))->values();

        // Build matrix: category -> branch -> total
        $matrix = [];
        $branchTotals             = [];
        $categoryTotals           = [];
        $operationalBranchTotals  = [];
        $overheadBranchTotals     = [];
        $grandTotal               = 0;
        $operationalGrandTotal    = 0;
        $overheadGrandTotal       = 0;

        foreach ($categories as $cat) {
            $matrix[$cat->id] = [];
            $categoryTotals[$cat->id] = 0;
        }

        foreach ($branches as $branch) {
            $branchTotals[$branch->id]            = 0;
            $operationalBranchTotals[$branch->id] = 0;
            $overheadBranchTotals[$branch->id]    = 0;

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
                $matrix[$cat->id][$branch->id] = $amount;
                $branchTotals[$branch->id]     += $amount;
                $categoryTotals[$cat->id]      += $amount;
                $grandTotal                    += $amount;

                if (in_array($cat->name, $operationalNames)) {
                    $operationalBranchTotals[$branch->id] += $amount;
                    $operationalGrandTotal                += $amount;
                } elseif (in_array($cat->name, $overheadNames)) {
                    $overheadBranchTotals[$branch->id] += $amount;
                    $overheadGrandTotal                += $amount;
                }
            }
        }

        return view('reports.consolidated', compact(
            'branches', 'categories', 'operationalCats', 'overheadCats',
            'matrix', 'branchTotals', 'categoryTotals', 'grandTotal',
            'operationalBranchTotals', 'overheadBranchTotals',
            'operationalGrandTotal', 'overheadGrandTotal',
            'year', 'month'
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

            $totalSales    = (float) SalesEntry::whereIn('period_id', $periodIds)->sum('amount');
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
