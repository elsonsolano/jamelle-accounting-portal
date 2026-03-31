@extends('layouts.app')
@section('title', 'Consolidated Expense Report')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-2 mb-6">
    <h1 class="text-xl font-bold text-gray-800">Consolidated Expense Report</h1>
    <button onclick="window.print()"
            class="flex items-center gap-2 bg-gray-700 text-white text-sm px-4 py-2 rounded hover:bg-gray-800 no-print">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
        </svg>
        Export PDF
    </button>
</div>

{{-- Filters --}}
<form method="GET" class="no-print flex flex-wrap gap-3 mb-6 bg-white p-4 rounded shadow-sm border border-gray-100">
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

            {{-- ── Operational Expenses ──────────────────────────────────── --}}
            <tr class="bg-blue-600 text-white">
                <td class="px-3 py-2 font-bold uppercase tracking-wide text-xs sticky left-0 bg-blue-600"
                    colspan="{{ $branches->count() + 2 }}">▌ Operational Expenses</td>
            </tr>
            @foreach($operationalCats as $cat)
                @php $rowTotal = $categoryTotals[$cat->id] ?? 0; @endphp
                <tr class="hover:bg-gray-50 {{ $rowTotal == 0 ? 'opacity-40' : '' }}">
                    <td class="px-3 py-2 text-gray-700 sticky left-0 bg-white pl-5">{{ $cat->name }}</td>
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
            <tr class="bg-blue-50 font-bold text-xs border-t-2 border-blue-200">
                <td class="px-3 py-2 text-blue-800 sticky left-0 bg-blue-50">Operational Subtotal</td>
                @foreach($branches as $branch)
                    <td class="px-3 py-2 text-right tabular-nums text-blue-800">
                        {{ number_format($operationalBranchTotals[$branch->id] ?? 0, 2) }}
                    </td>
                @endforeach
                <td class="px-3 py-2 text-right tabular-nums text-blue-900 bg-blue-100">
                    {{ number_format($operationalGrandTotal, 2) }}
                </td>
            </tr>

            {{-- ── Overhead Expenses ─────────────────────────────────────── --}}
            <tr class="bg-amber-600 text-white">
                <td class="px-3 py-2 font-bold uppercase tracking-wide text-xs sticky left-0 bg-amber-600"
                    colspan="{{ $branches->count() + 2 }}">▌ Overhead Expenses</td>
            </tr>
            @foreach($overheadCats as $cat)
                @php $rowTotal = $categoryTotals[$cat->id] ?? 0; @endphp
                <tr class="hover:bg-gray-50 {{ $rowTotal == 0 ? 'opacity-40' : '' }}">
                    <td class="px-3 py-2 text-gray-700 sticky left-0 bg-white pl-5">{{ $cat->name }}</td>
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
            <tr class="bg-amber-50 font-bold text-xs border-t-2 border-amber-200">
                <td class="px-3 py-2 text-amber-800 sticky left-0 bg-amber-50">Overhead Subtotal</td>
                @foreach($branches as $branch)
                    <td class="px-3 py-2 text-right tabular-nums text-amber-800">
                        {{ number_format($overheadBranchTotals[$branch->id] ?? 0, 2) }}
                    </td>
                @endforeach
                <td class="px-3 py-2 text-right tabular-nums text-amber-900 bg-amber-100">
                    {{ number_format($overheadGrandTotal, 2) }}
                </td>
            </tr>

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

@push('scripts')
<style>
@media print {
    @page { size: landscape; margin: 10mm; }

    nav, .no-print { display: none !important; }

    body { background: white; font-size: 10px; }

    main { padding: 0 !important; max-width: 100% !important; }

    .overflow-x-auto { overflow: visible !important; }

    table { width: 100%; border-collapse: collapse; font-size: 9px; }

    thead th, tbody td, tfoot td {
        padding: 4px 6px !important;
        border: 1px solid #d1d5db;
    }

    /* Keep section header colors */
    .bg-blue-600  { background-color: #2563eb !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .bg-amber-600 { background-color: #d97706 !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .bg-blue-50   { background-color: #eff6ff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .bg-amber-50  { background-color: #fffbeb !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .bg-indigo-50 { background-color: #eef2ff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .bg-gray-800  { background-color: #1f2937 !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .bg-indigo-900{ background-color: #312e81 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

    /* Unfix sticky column for print */
    .sticky { position: static !important; }

    h1 { font-size: 14px; margin-bottom: 6px; }
    p.text-sm { margin-bottom: 6px; }

    tr { page-break-inside: avoid; }
}
</style>
@endpush
