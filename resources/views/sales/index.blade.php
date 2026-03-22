@extends('layouts.app')
@section('title', 'Sales')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-gray-800">Daily Sales</h1>
        <p class="text-sm text-gray-500 mt-0.5">Select a period to encode daily gross sales per branch.</p>
    </div>
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
    <div class="flex items-end gap-2">
        <button type="submit" class="bg-gray-700 text-white text-sm px-4 py-1.5 rounded hover:bg-gray-800">
            Filter
        </button>
        <a href="{{ route('sales.index') }}" class="text-sm text-gray-500 hover:underline py-1.5">Clear</a>
    </div>
</form>

<div class="bg-white rounded shadow overflow-hidden border border-gray-100">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
                <th class="px-4 py-3 text-left">Branch</th>
                <th class="px-4 py-3 text-left">Period</th>
                <th class="px-4 py-3 text-right">Total Gross Sales</th>
                <th class="px-4 py-3 text-center">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($periods as $period)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $period->branch->name }}</td>
                    <td class="px-4 py-3 text-gray-600">
                        {{ \Carbon\Carbon::create($period->year, $period->month)->format('F Y') }}
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums">
                        @if($period->sales_entries_sum_amount)
                            <span class="font-semibold text-emerald-700">
                                ₱{{ number_format($period->sales_entries_sum_amount, 2) }}
                            </span>
                        @else
                            <span class="text-gray-400 italic text-xs">No entries yet</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        <a href="{{ route('sales.show', $period) }}"
                           class="text-indigo-600 hover:underline font-medium">
                            {{ $period->sales_entries_sum_amount ? 'View / Edit' : 'Encode Sales' }}
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-10 text-center text-gray-400">
                        No expense periods found. Create an expense period first, then encode sales here.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $periods->links() }}
</div>
@endsection
