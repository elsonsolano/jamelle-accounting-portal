<?php

namespace App\Services;

use App\Models\ExpensePeriod;
use Illuminate\Support\Collection;

class ExpenseCalculatorService
{
    /**
     * Returns entries with cumulative running total.
     */
    public function runningTotals(ExpensePeriod $period): Collection
    {
        $entries = $period->expenseEntries()->with('category')->get();

        $running = 0;

        return $entries->map(function ($entry) use (&$running) {
            $running += (float) $entry->amount;
            $entry->running_total = $running;
            return $entry;
        });
    }

    /**
     * Returns sum of amounts grouped by category name.
     */
    public function categoryTotals(ExpensePeriod $period): Collection
    {
        return $period->expenseEntries()
            ->with('category')
            ->get()
            ->groupBy(fn($e) => $e->category->name)
            ->map(fn($group) => $group->sum('amount'))
            ->sortKeys();
    }

    /**
     * Returns total expense for the period.
     */
    public function periodTotal(ExpensePeriod $period): float
    {
        return (float) $period->expenseEntries()->sum('amount');
    }

    /**
     * Returns sum of gross sales across all branches for the period.
     */
    public function grossSalesTotal(ExpensePeriod $period): float
    {
        return (float) $period->grossSales()->sum('amount');
    }

    /**
     * Returns grossSalesTotal - periodTotal.
     */
    public function operatingIncome(ExpensePeriod $period): float
    {
        return $this->grossSalesTotal($period) - $this->periodTotal($period);
    }

    /**
     * Returns operatingIncome - vat_itr_estimate.
     */
    public function netOperatingIncome(ExpensePeriod $period): float
    {
        return $this->operatingIncome($period) - (float) $period->vat_itr_estimate;
    }
}
