@extends('layouts.app')
@section('title', 'Consolidated Expense Report')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-gray-800">Consolidated Expense Report</h1>
</div>

{{-- Filters --}}
<form method="GET" class="flex flex-wrap gap-3 mb-6 bg-white p-4 rounded shadow-sm border border-gray-100">
    <div>
        <label class="block text-xs text-gray-500 mb-1">Month</label>
        <select name="month" class="text-sm border-gray-300 rounded px-2 py-1.5 focus:ring-indigo-500">
            @foreach(range(1,12) as $m)
                <option value="{{ $m }}" @selected($month == $m)>
                    {{ \Carbon\Carbon::create(null, $m)->format('F') }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs text-gray-500 mb-1">Year</label>
        <input type="number" name="year" value="{{ $year }}" min="2000" max="2100"
               class="text-sm border-gray-300 rounded px-2 py-1.5 w-24 focus:ring-indigo-500">
    </div>
    <div class="flex items-end">
        <button type="submit" class="bg-gray-700 text-white text-sm px-4 py-1.5 rounded hover:bg-gray-800">
            View
        </button>
    </div>
</form>

<p class="text-sm text-gray-500 mb-4 font-medium">
    {{ \Carbon\Carbon::create($year, $month)->format('F Y') }}
</p>

<div class="overflow-x-auto bg-white rounded shadow border border-gray-100">
    <table class="min-w-full text-xs">
        <thead class="bg-gray-50 text-gray-600 uppercase">
            <tr>
                <th class="px-3 py-2 text-left sticky left-0 bg-gray-50 z-10">Category</th>
                @foreach($branches as $branch)
                    <th class="px-3 py-2 text-right whitespace-nowrap">{{ $branch->name }}</th>
                @endforeach
                <th class="px-3 py-2 text-right font-bold bg-indigo-50">Total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($categories as $cat)
                @php $rowTotal = $categoryTotals[$cat->id] ?? 0; @endphp
                <tr class="hover:bg-gray-50 {{ $rowTotal == 0 ? 'opacity-40' : '' }}">
                    <td class="px-3 py-2 text-gray-700 sticky left-0 bg-white">{{ $cat->name }}</td>
                    @foreach($branches as $branch)
                        <td class="px-3 py-2 text-right tabular-nums text-gray-600">
                            @if(($matrix[$cat->id][$branch->id] ?? 0) > 0)
                                {{ number_format($matrix[$cat->id][$branch->id], 2) }}
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                    @endforeach
                    <td class="px-3 py-2 text-right tabular-nums font-semibold text-indigo-700 bg-indigo-50">
                        {{ number_format($rowTotal, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="bg-gray-800 text-white font-bold text-xs">
                <td class="px-3 py-2 sticky left-0 bg-gray-800">Grand Total</td>
                @foreach($branches as $branch)
                    <td class="px-3 py-2 text-right tabular-nums">
                        {{ number_format($branchTotals[$branch->id] ?? 0, 2) }}
                    </td>
                @endforeach
                <td class="px-3 py-2 text-right tabular-nums bg-indigo-900">
                    {{ number_format($grandTotal, 2) }}
                </td>
            </tr>
        </tfoot>
    </table>
</div>
@endsection
