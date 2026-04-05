<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ExpenseEntry;
use App\Models\ExpensePeriod;
use App\Models\GrossSales;
use App\Models\SalesEntry;
use Illuminate\Http\Request;

class VoiceApiController extends Controller
{
    public function branches()
    {
        $branches = Branch::orderBy('name')->get(['id', 'name', 'is_cost_center']);

        return response()->json($branches->map(fn($b) => [
            'id'             => $b->id,
            'name'           => $b->name,
            'is_cost_center' => (bool) $b->is_cost_center,
        ]));
    }

    public function summary(Request $request)
    {
        $month = (int) $request->input('month', now()->month);
        $year  = (int) $request->input('year',  now()->year);
        $branchSlug = $request->input('branch');

        $branchQuery = Branch::orderBy('name');
        if ($branchSlug) {
            $branchQuery->whereRaw('LOWER(REPLACE(name, " ", "-")) = ?', [strtolower($branchSlug)]);
        }
        $branches = $branchQuery->get();

        if ($branchSlug && $branches->isEmpty()) {
            return response()->json(['error' => 'Branch not found'], 404);
        }

        $rows = [];
        foreach ($branches as $branch) {
            $period = ExpensePeriod::where('branch_id', $branch->id)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            $periodIds = $period ? [$period->id] : [];

            $totalSales    = (float) SalesEntry::whereIn('period_id', $periodIds)->sum('amount');
            $totalExpenses = (float) ExpenseEntry::whereIn('period_id', $periodIds)->sum('amount');
            $vatItr        = (float) GrossSales::whereIn('period_id', $periodIds)->sum('vat_itr');
            $operating     = $totalSales - $totalExpenses;
            $net           = $operating - $vatItr;

            $rows[] = [
                'branch'               => $branch->name,
                'is_cost_center'       => (bool) $branch->is_cost_center,
                'total_sales'          => $branch->is_cost_center ? null : $totalSales,
                'total_expenses'       => $totalExpenses,
                'operating_income'     => $branch->is_cost_center ? null : $operating,
                'vat_itr'              => $branch->is_cost_center ? null : $vatItr,
                'net_operating_income' => $branch->is_cost_center ? null : $net,
            ];
        }

        $response = [
            'period' => $this->monthName($month) . ' ' . $year,
            'month'  => $month,
            'year'   => $year,
        ];

        if ($branchSlug) {
            $response = array_merge($response, $rows[0]);
        } else {
            $nonCost = collect($rows)->where('is_cost_center', false);
            $response['branches']          = $rows;
            $response['grand_total_sales'] = $nonCost->sum('total_sales');
            $response['grand_total_expenses'] = collect($rows)->sum('total_expenses');
            $response['grand_operating_income'] = $nonCost->sum('operating_income') - collect($rows)->where('is_cost_center', true)->sum('total_expenses');
            $response['grand_vat_itr']          = $nonCost->sum('vat_itr');
            $response['grand_net_operating_income'] = $response['grand_operating_income'] - $response['grand_vat_itr'];
        }

        return response()->json($response);
    }

    public function expenses(Request $request)
    {
        $month = (int) $request->input('month', now()->month);
        $year  = (int) $request->input('year',  now()->year);
        $branchSlug = $request->input('branch');

        $branchQuery = Branch::orderBy('name');
        if ($branchSlug) {
            $branchQuery->whereRaw('LOWER(REPLACE(name, " ", "-")) = ?', [strtolower($branchSlug)]);
        }
        $branches = $branchQuery->get();

        if ($branchSlug && $branches->isEmpty()) {
            return response()->json(['error' => 'Branch not found'], 404);
        }

        $result = [];
        foreach ($branches as $branch) {
            $period = ExpensePeriod::where('branch_id', $branch->id)
                ->where('month', $month)
                ->where('year', $year)
                ->with('expenseEntries.category')
                ->first();

            $categories = [];
            if ($period) {
                $grouped = $period->expenseEntries->groupBy('category.name');
                foreach ($grouped as $catName => $entries) {
                    $categories[] = [
                        'category' => $catName,
                        'total'    => (float) $entries->sum('amount'),
                    ];
                }
                usort($categories, fn($a, $b) => $b['total'] <=> $a['total']);
            }

            $result[] = [
                'branch'          => $branch->name,
                'period'          => $this->monthName($month) . ' ' . $year,
                'total_expenses'  => (float) collect($categories)->sum('total'),
                'breakdown'       => $categories,
            ];
        }

        return response()->json($branchSlug ? $result[0] : $result);
    }

    public function sales(Request $request)
    {
        $branchSlug = $request->input('branch');
        $date       = $request->input('date');   // e.g. 2026-03-15
        $from       = $request->input('from');   // e.g. 2026-03-01
        $to         = $request->input('to');     // e.g. 2026-03-31

        $branchQuery = Branch::where('is_cost_center', false)->orderBy('name');
        if ($branchSlug) {
            $branchQuery->whereRaw('LOWER(REPLACE(name, " ", "-")) = ?', [strtolower($branchSlug)]);
        }
        $branches = $branchQuery->get();

        if ($branchSlug && $branches->isEmpty()) {
            return response()->json(['error' => 'Branch not found'], 404);
        }

        $result = [];
        foreach ($branches as $branch) {
            $periodIds = ExpensePeriod::where('branch_id', $branch->id)->pluck('id');

            $query = SalesEntry::whereIn('period_id', $periodIds)->orderBy('date');

            if ($date) {
                $query->whereDate('date', $date);
            } elseif ($from && $to) {
                $query->whereBetween('date', [$from, $to]);
            } elseif ($from) {
                $query->whereDate('date', '>=', $from);
            } elseif ($to) {
                $query->whereDate('date', '<=', $to);
            }

            $entries = $query->get();

            $result[] = [
                'branch'      => $branch->name,
                'total_sales' => (float) $entries->sum('amount'),
                'entries'     => $entries->map(fn($e) => [
                    'date'   => $e->date->format('Y-m-d'),
                    'amount' => (float) $e->amount,
                    'notes'  => $e->notes,
                ]),
            ];
        }

        return response()->json($branchSlug ? $result[0] : $result);
    }

    private function monthName(int $month): string
    {
        return \DateTime::createFromFormat('!m', $month)->format('F');
    }
}
