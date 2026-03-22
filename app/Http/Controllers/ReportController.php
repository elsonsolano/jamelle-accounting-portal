<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ExpenseCategory;
use App\Models\ExpensePeriod;
use App\Services\ExpenseCalculatorService;

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
}
