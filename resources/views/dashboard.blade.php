@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

{{-- ── Header ─────────────────────────────────────────────────────────────── --}}
<div class="flex flex-wrap items-center justify-between gap-2 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            @php
                $hour = $now->hour;
                $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
            @endphp
            {{ $greeting }}, {{ auth()->user()->name }} 👋
        </h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ $now->format('l, F j, Y') }}</p>
    </div>
    <div class="text-right">
        <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Reporting Period</p>
        <p class="text-sm font-semibold text-indigo-700">{{ $now->format('F Y') }}</p>
    </div>
</div>

{{-- ── Summary Cards ───────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Gross Sales</p>
            <span class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center text-emerald-600 text-sm font-bold">₱</span>
        </div>
        <p class="text-2xl font-bold text-emerald-700 tabular-nums">₱{{ number_format($totalSalesThisMonth, 0) }}</p>
        <p class="text-xs text-gray-400 mt-1">{{ $now->format('F Y') }}</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Total Expenses</p>
            <span class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center text-red-500 text-sm font-bold">−</span>
        </div>
        <p class="text-2xl font-bold text-red-600 tabular-nums">₱{{ number_format($totalExpensesThisMonth, 0) }}</p>
        <p class="text-xs text-gray-400 mt-1">Branches only</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Operating Income</p>
            <span class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-bold
                {{ $operatingIncomeThisMonth >= 0 ? 'bg-indigo-100 text-indigo-600' : 'bg-red-100 text-red-500' }}">
                {{ $operatingIncomeThisMonth >= 0 ? '↑' : '↓' }}
            </span>
        </div>
        <p class="text-2xl font-bold tabular-nums {{ $operatingIncomeThisMonth >= 0 ? 'text-indigo-700' : 'text-red-600' }}">
            ₱{{ number_format(abs($operatingIncomeThisMonth), 0) }}
        </p>
        <p class="text-xs mt-1 {{ $operatingIncomeThisMonth >= 0 ? 'text-indigo-400' : 'text-red-400' }}">
            {{ $operatingIncomeThisMonth >= 0 ? 'Profit' : 'Loss' }} this month
        </p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Overhead</p>
            <span class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center text-amber-600 text-sm font-bold">HO</span>
        </div>
        <p class="text-2xl font-bold text-amber-700 tabular-nums">₱{{ number_format($overheadExpensesThisMonth, 0) }}</p>
        <p class="text-xs text-gray-400 mt-1">Head Office expenses</p>
    </div>

</div>

{{-- ── Main Content ─────────────────────────────────────────────────────────── --}}
<div class="flex flex-col lg:flex-row gap-6 items-start">

    {{-- Left: Tables --}}
    <div class="flex-1 min-w-0 space-y-5">

        {{-- Revenue Branches --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-gray-800">Branch Performance</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Revenue branches · {{ $now->format('F Y') }}</p>
                </div>
                <a href="{{ route('expense-periods.index') }}" class="text-xs text-indigo-600 hover:underline">View all periods →</a>
            </div>
            <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-5 py-3 text-left">Branch</th>
                        <th class="px-5 py-3 text-right">Gross Sales</th>
                        <th class="px-5 py-3 text-right">Expenses</th>
                        <th class="px-5 py-3 text-right">Operating Income</th>
                        <th class="px-5 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($revenueBranches as $branch)
                        @php
                            $period   = $revenuePeriods->firstWhere('branch_id', $branch->id);
                            $sales    = (float) ($period?->sales_entries_sum_amount ?? 0);
                            $expenses = (float) ($period?->expense_entries_sum_amount ?? 0);
                            $income   = $sales - $expenses;
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3.5 font-medium text-gray-800">{{ $branch->name }}</td>
                            <td class="px-5 py-3.5 text-right tabular-nums text-emerald-700 font-medium">
                                @if($sales > 0) ₱{{ number_format($sales, 2) }}
                                @else <span class="text-gray-300 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right tabular-nums text-red-600">
                                @if($expenses > 0) ₱{{ number_format($expenses, 2) }}
                                @else <span class="text-gray-300 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right tabular-nums font-semibold">
                                @if($period)
                                    <span class="{{ $income >= 0 ? 'text-indigo-700' : 'text-red-600' }}">
                                        ₱{{ number_format($income, 2) }}
                                    </span>
                                @else
                                    <span class="text-gray-300 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if($period)
                                    <a href="{{ route('expense-periods.show', $period) }}"
                                       class="inline-block text-xs px-2.5 py-1 rounded-full font-medium bg-emerald-100 text-emerald-700 hover:bg-emerald-200">
                                        Open
                                    </a>
                                @else
                                    <span class="inline-block text-xs px-2.5 py-1 rounded-full bg-gray-100 text-gray-400">No period</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400 text-sm">No revenue branches found.</td></tr>
                    @endforelse
                </tbody>
                @if($revenueBranches->count() > 0)
                <tfoot class="bg-gray-50 border-t-2 border-gray-200 text-sm font-semibold">
                    <tr>
                        <td class="px-5 py-3 text-gray-600">Total</td>
                        <td class="px-5 py-3 text-right tabular-nums text-emerald-700">₱{{ number_format($totalSalesThisMonth, 2) }}</td>
                        <td class="px-5 py-3 text-right tabular-nums text-red-600">₱{{ number_format($totalExpensesThisMonth, 2) }}</td>
                        <td class="px-5 py-3 text-right tabular-nums {{ $operatingIncomeThisMonth >= 0 ? 'text-indigo-700' : 'text-red-600' }}">
                            ₱{{ number_format($operatingIncomeThisMonth, 2) }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
                @endif
            </table>
            </div>{{-- /overflow-x-auto --}}
        </div>

        {{-- Overhead / Cost Centers --}}
        @if($costCenters->count() > 0)
        <div class="bg-white rounded-xl border border-amber-200 shadow-sm">
            <div class="px-5 py-4 border-b border-amber-100 flex items-center justify-between bg-amber-50">
                <div>
                    <h2 class="font-semibold text-amber-800">Overhead / Cost Centers</h2>
                    <p class="text-xs text-amber-500 mt-0.5">Not included in operating income · {{ $now->format('F Y') }}</p>
                </div>
            </div>
            <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-amber-50 text-amber-600 text-xs uppercase">
                    <tr>
                        <th class="px-5 py-3 text-left">Cost Center</th>
                        <th class="px-5 py-3 text-right">Expenses This Month</th>
                        <th class="px-5 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-amber-50">
                    @foreach($costCenters as $branch)
                        @php
                            $period   = $overheadPeriods->firstWhere('branch_id', $branch->id);
                            $expenses = (float) ($period?->expense_entries_sum_amount ?? 0);
                        @endphp
                        <tr class="hover:bg-amber-50">
                            <td class="px-5 py-3.5 font-medium text-gray-800">
                                {{ $branch->name }}
                                <span class="ml-2 text-xs px-1.5 py-0.5 rounded bg-amber-100 text-amber-600 font-medium">overhead</span>
                            </td>
                            <td class="px-5 py-3.5 text-right tabular-nums text-amber-700 font-medium">
                                @if($expenses > 0) ₱{{ number_format($expenses, 2) }}
                                @else <span class="text-gray-300 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if($period)
                                    <a href="{{ route('expense-periods.show', $period) }}"
                                       class="inline-block text-xs px-2.5 py-1 rounded-full font-medium bg-amber-100 text-amber-700 hover:bg-amber-200">
                                        Open
                                    </a>
                                @else
                                    <span class="inline-block text-xs px-2.5 py-1 rounded-full bg-gray-100 text-gray-400">No period</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>{{-- /overflow-x-auto --}}
        </div>
        @endif

        {{-- All-time strip --}}
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">All-time Sales</p>
                    <p class="text-lg font-bold text-emerald-700 tabular-nums mt-0.5">₱{{ number_format($allTimeSales, 2) }}</p>
                </div>
                <span class="text-2xl opacity-20">📈</span>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">All-time Expenses</p>
                    <p class="text-lg font-bold text-red-600 tabular-nums mt-0.5">₱{{ number_format($allTimeExpenses, 2) }}</p>
                </div>
                <span class="text-2xl opacity-20">📊</span>
            </div>
        </div>

    </div>

    {{-- Right: Recent Activity --}}
    <div class="w-full lg:w-80 lg:shrink-0 space-y-4">

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-4 py-3.5 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-800 text-sm">Recent Sales</h2>
                <a href="{{ route('expense-periods.index') }}" class="text-xs text-emerald-600 hover:underline">View all →</a>
            </div>
            <ul class="divide-y divide-gray-50">
                @forelse($recentSales as $sale)
                    <li class="px-4 py-3 hover:bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-gray-700 truncate">{{ $sale->period->branch->name }}</p>
                                <p class="text-xs text-gray-400">
                                    {{ $sale->date->format('M j, Y') }}
                                    @if($sale->creator) · {{ $sale->creator->name }} @endif
                                </p>
                            </div>
                            <span class="text-sm font-semibold text-emerald-700 tabular-nums ml-3 shrink-0">
                                ₱{{ number_format($sale->amount, 2) }}
                            </span>
                        </div>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-gray-400 text-xs">No sales recorded yet.</li>
                @endforelse
            </ul>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-4 py-3.5 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-800 text-sm">Recent Expenses</h2>
                <a href="{{ route('expense-periods.index') }}" class="text-xs text-indigo-600 hover:underline">View all →</a>
            </div>
            <ul class="divide-y divide-gray-50">
                @forelse($recentExpenses as $entry)
                    <li class="px-4 py-3 hover:bg-gray-50">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-gray-700 truncate">
                                    {{ $entry->particular ?: $entry->category->name }}
                                </p>
                                <p class="text-xs text-gray-400 truncate">
                                    {{ $entry->period->branch->name }} · {{ $entry->category->name }}
                                </p>
                                @if($entry->creator)
                                    <p class="text-xs text-gray-300">by {{ $entry->creator->name }}</p>
                                @endif
                            </div>
                            <span class="text-xs font-semibold text-red-600 tabular-nums shrink-0">
                                ₱{{ number_format($entry->amount, 2) }}
                            </span>
                        </div>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-gray-400 text-xs">No expenses recorded yet.</li>
                @endforelse
            </ul>
        </div>

    </div>
</div>

@endsection
