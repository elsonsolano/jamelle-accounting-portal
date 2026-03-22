@extends('layouts.app')
@section('title', 'Expense Periods')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-gray-800">Expense Periods</h1>
    @can('create', \App\Models\ExpensePeriod::class)
        <a href="{{ route('expense-periods.create') }}"
           class="inline-flex items-center gap-1 bg-indigo-600 text-white text-sm px-4 py-2 rounded hover:bg-indigo-700">
            + New Period
        </a>
    @endcan
</div>

{{-- Filters --}}
<form method="GET" class="flex flex-wrap gap-3 mb-6 bg-white p-4 rounded shadow-sm border border-gray-100">
    <div>
        <label class="block text-xs text-gray-500 mb-1">Branch</label>
        <select name="branch_id" class="text-sm border-gray-300 rounded px-2 py-1.5 focus:ring-indigo-500">
            <option value="">All Branches</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>
                    {{ $branch->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs text-gray-500 mb-1">Month</label>
        <select name="month" class="text-sm border-gray-300 rounded px-2 py-1.5 focus:ring-indigo-500">
            <option value="">All Months</option>
            @foreach(range(1,12) as $m)
                <option value="{{ $m }}" @selected(request('month') == $m)>
                    {{ \Carbon\Carbon::create(null, $m)->format('F') }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="flex items-end">
        <button type="submit" class="bg-gray-700 text-white text-sm px-4 py-1.5 rounded hover:bg-gray-800">
            Filter
        </button>
        <a href="{{ route('expense-periods.index') }}" class="ml-2 text-sm text-gray-500 hover:underline py-1.5">
            Clear
        </a>
    </div>
</form>

<div class="bg-white rounded shadow overflow-hidden border border-gray-100">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
                <th class="px-4 py-3 text-left">Branch</th>
                <th class="px-4 py-3 text-left">Period</th>
                <th class="px-4 py-3 text-right">Running Total</th>
                <th class="px-4 py-3 text-right">VAT/ITR Estimate</th>
                <th class="px-4 py-3 text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($periods as $period)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $period->branch->name }}</td>
                    <td class="px-4 py-3 text-gray-600">
                        {{ \Carbon\Carbon::create($period->year, $period->month)->format('F Y') }}
                    </td>
                    <td class="px-4 py-3 text-right font-medium text-indigo-700 tabular-nums">
                        ₱{{ number_format($period->expense_entries_sum_amount ?? 0, 2) }}
                    </td>
                    <td class="px-4 py-3 text-right text-gray-600">
                        ₱{{ number_format($period->vat_itr_estimate, 2) }}
                    </td>
                    <td class="px-4 py-3 text-center space-x-3">
                        <a href="{{ route('expense-periods.show', $period) }}"
                           class="text-indigo-600 hover:underline">Open</a>
                        <a href="{{ route('expense-periods.edit', $period) }}"
                           class="text-gray-500 hover:underline">Edit</a>
                        @can('delete', $period)
                        <form method="POST" action="{{ route('expense-periods.destroy', $period) }}" class="inline"
                              onsubmit="return confirm('Delete this period and all its entries? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-500 hover:underline">Delete</button>
                        </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-gray-400">No periods found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $periods->links() }}
</div>
@endsection
