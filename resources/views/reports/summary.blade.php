@extends('layouts.app')
@section('title', 'Period Summary')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-gray-800">
        Summary — {{ $period->branch->name }}
        {{ \Carbon\Carbon::create($period->year, $period->month)->format('F Y') }}
    </h1>
    <a href="{{ route('expense-periods.show', $period) }}" class="text-sm text-gray-500 hover:underline">
        &larr; Back to Sheet
    </a>
</div>

<div class="grid grid-cols-2 gap-6">
    <div class="bg-white rounded shadow border border-gray-100 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 font-semibold text-sm text-gray-700">Category Breakdown</div>
        <table class="min-w-full text-sm">
            <tbody class="divide-y divide-gray-100">
                @foreach($categoryTotals as $name => $total)
                    <tr>
                        <td class="px-4 py-2 text-gray-700">{{ $name }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-gray-800">
                            ₱{{ number_format($total, 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="bg-gray-50 font-bold">
                    <td class="px-4 py-2 text-gray-700">Total Expenses</td>
                    <td class="px-4 py-2 text-right tabular-nums text-indigo-700">
                        ₱{{ number_format($periodTotal, 2) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="space-y-4">
        <div class="bg-white rounded shadow border border-gray-100 p-4 space-y-3">
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Gross Sales</span>
                <span class="tabular-nums font-medium">₱{{ number_format($grossSales, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Total Expenses</span>
                <span class="tabular-nums font-medium text-red-600">₱{{ number_format($periodTotal, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm border-t pt-2">
                <span class="text-gray-700 font-semibold">Operating Income</span>
                <span class="tabular-nums font-bold {{ $operating >= 0 ? 'text-green-700' : 'text-red-700' }}">
                    ₱{{ number_format($operating, 2) }}
                </span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">VAT/ITR Estimate</span>
                <span class="tabular-nums font-medium text-orange-600">
                    ₱{{ number_format($period->vat_itr_estimate, 2) }}
                </span>
            </div>
            <div class="flex justify-between text-sm border-t pt-2 bg-indigo-50 -mx-4 px-4 py-2 rounded-b">
                <span class="text-indigo-800 font-bold uppercase text-xs tracking-wide">Net Operating Income</span>
                <span class="tabular-nums font-bold text-lg {{ $net >= 0 ? 'text-indigo-700' : 'text-red-700' }}">
                    ₱{{ number_format($net, 2) }}
                </span>
            </div>
        </div>
    </div>
</div>
@endsection
