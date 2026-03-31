@extends('layouts.app')
@section('title', 'Branch Income Summary')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-2 mb-6">
    <h1 class="text-xl font-bold text-gray-800">Branch Income Summary</h1>
    <span class="text-sm text-gray-500 font-medium">
        @if($isSingleMonth)
            {{ \Carbon\Carbon::create($fromYear, $fromMonth)->format('F Y') }}
        @else
            {{ \Carbon\Carbon::create($fromYear, $fromMonth)->format('M Y') }}
            &ndash;
            {{ \Carbon\Carbon::create($toYear, $toMonth)->format('M Y') }}
        @endif
    </span>
</div>

{{-- Filters --}}
<form method="GET" class="flex flex-col sm:flex-row flex-wrap gap-4 mb-6 bg-white p-4 rounded shadow-sm border border-gray-100 items-start sm:items-end">

    {{-- Preset pills --}}
    <div class="flex flex-wrap gap-2 items-center">
        <span class="text-xs text-gray-400 mr-1">Quick:</span>
        @php
            $presets = [
                'this_month' => 'This Month',
                'last_3'     => 'Last 3 Months',
                'last_6'     => 'Last 6 Months',
                'ytd'        => 'Year to Date',
                'this_year'  => 'Full Year',
            ];
        @endphp
        @foreach($presets as $key => $label)
            <button type="button" onclick="applyPreset('{{ $key }}')"
                    class="text-xs px-3 py-1.5 rounded border border-gray-300 text-gray-600 hover:border-indigo-500 hover:text-indigo-600 transition-colors">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="flex gap-3 items-end sm:ml-auto flex-wrap">
        {{-- From --}}
        <div>
            <label class="block text-xs text-gray-500 mb-1">From</label>
            <div class="flex gap-1">
                <select id="from_month" name="from_month" class="text-sm border-gray-300 rounded px-2 py-1.5 focus:ring-indigo-500">
                    @foreach(range(1, 12) as $m)
                        <option value="{{ $m }}" @selected($fromMonth == $m)>
                            {{ \Carbon\Carbon::create(null, $m)->format('M') }}
                        </option>
                    @endforeach
                </select>
                <input id="from_year" type="number" name="from_year" value="{{ $fromYear }}"
                       min="2000" max="2100"
                       class="text-sm border-gray-300 rounded px-2 py-1.5 w-20 focus:ring-indigo-500">
            </div>
        </div>

        {{-- To --}}
        <div>
            <label class="block text-xs text-gray-500 mb-1">To</label>
            <div class="flex gap-1">
                <select id="to_month" name="to_month" class="text-sm border-gray-300 rounded px-2 py-1.5 focus:ring-indigo-500">
                    @foreach(range(1, 12) as $m)
                        <option value="{{ $m }}" @selected($toMonth == $m)>
                            {{ \Carbon\Carbon::create(null, $m)->format('M') }}
                        </option>
                    @endforeach
                </select>
                <input id="to_year" type="number" name="to_year" value="{{ $toYear }}"
                       min="2000" max="2100"
                       class="text-sm border-gray-300 rounded px-2 py-1.5 w-20 focus:ring-indigo-500">
            </div>
        </div>

        <button type="submit"
                class="bg-gray-700 text-white text-sm px-4 py-1.5 rounded hover:bg-gray-800">
            View
        </button>
    </div>
</form>

{{-- Table --}}
<div class="overflow-x-auto bg-white rounded-lg shadow-sm border border-gray-200">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
            <tr>
                <th class="px-5 py-3 text-left font-semibold">Branch</th>
                <th class="px-5 py-3 text-right font-semibold">Total Sales</th>
                <th class="px-5 py-3 text-right font-semibold">Total Expenses</th>
                <th class="px-5 py-3 text-right font-semibold">Operating Income</th>
                <th class="px-5 py-3 text-right font-semibold">VAT / ITR</th>
                <th class="px-5 py-3 text-right font-semibold">Net Operating Income</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($rows as $row)
            <tr x-data="{
                    vatItr: {{ $row['vat_itr'] }},
                    operatingIncome: {{ $row['operating_income'] }},
                    get net() { return this.operatingIncome - this.vatItr; },
                    editing: false,
                    saving: false,
                    saved: false,
                    editVal: {{ $row['vat_itr'] }},
                    startEdit() { this.editVal = this.vatItr; this.editing = true; this.$nextTick(() => this.$refs.vatInput?.focus()); },
                    cancel() { this.editing = false; },
                    async save() {
                        this.saving = true;
                        try {
                            await fetch('/gross-sales', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({
                                    period_id: {{ $row['period_id'] ?? 'null' }},
                                    branch_id: {{ $row['branch']->id }},
                                    vat_itr: parseFloat(this.editVal) || 0
                                })
                            });
                            this.vatItr = parseFloat(this.editVal) || 0;
                            this.editing = false;
                            this.saved = true;
                            setTimeout(() => this.saved = false, 2000);
                        } finally {
                            this.saving = false;
                        }
                    }
                }"
                class="hover:bg-gray-50 transition-colors">

                <td class="px-5 py-3 font-medium text-gray-800">
                    {{ $row['branch']->name }}
                    @if($row['is_cost_center'])
                        <span class="ml-1 text-xs text-gray-400 font-normal">(cost center)</span>
                    @endif
                </td>

                {{-- Total Sales --}}
                <td class="px-5 py-3 text-right tabular-nums text-gray-700">
                    @if($row['is_cost_center'])
                        <span class="text-gray-300">—</span>
                    @else
                        ₱{{ number_format($row['total_sales'], 2) }}
                    @endif
                </td>

                {{-- Total Expenses --}}
                <td class="px-5 py-3 text-right tabular-nums text-gray-700">
                    ₱{{ number_format($row['total_expenses'], 2) }}
                </td>

                {{-- Operating Income --}}
                <td class="px-5 py-3 text-right tabular-nums font-medium">
                    @if($row['is_cost_center'])
                        <span class="text-gray-300">—</span>
                    @else
                        <span class="{{ $row['operating_income'] >= 0 ? 'text-green-700' : 'text-red-600' }}">
                            ₱{{ number_format($row['operating_income'], 2) }}
                        </span>
                    @endif
                </td>

                {{-- VAT / ITR --}}
                <td class="px-5 py-3 text-right relative group/vat">
                    @if($row['is_cost_center'])
                        <span class="text-gray-300">—</span>
                    @elseif($isSingleMonth && $row['period_id'])
                        {{-- Editing state --}}
                        <div x-show="editing" x-cloak class="flex items-center justify-end gap-1">
                            <input x-ref="vatInput" type="number" x-model="editVal"
                                   @keydown.enter.prevent="save()" @keydown.escape="cancel()"
                                   min="0" step="0.01" :disabled="saving"
                                   class="w-36 text-right text-sm border-gray-300 rounded px-2 py-0.5 focus:ring-indigo-500 tabular-nums">
                            <button @click="save()" :disabled="saving"
                                    class="text-xs px-2 py-1 bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50">
                                Save
                            </button>
                            <button @click="cancel()" class="text-xs px-2 py-1 text-gray-500 hover:text-gray-700">
                                ✕
                            </button>
                        </div>
                        {{-- Display state --}}
                        <div x-show="!editing">
                            <span x-show="saved" x-cloak class="text-xs text-green-500 mr-1">Saved</span>
                            <span class="inline-block relative">
                                <span class="tabular-nums text-gray-700" x-text="'₱' + fmt(vatItr)">₱{{ number_format($row['vat_itr'], 2) }}</span>
                                <button @click="startEdit()"
                                        class="absolute -left-5 top-1/2 -translate-y-1/2 opacity-0 group-hover/vat:opacity-100 transition-opacity text-gray-400 hover:text-indigo-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                                    </svg>
                                </button>
                            </span>
                        </div>
                    @elseif($isSingleMonth && !$row['period_id'])
                        <span class="text-gray-400 text-xs">No period</span>
                    @else
                        <span class="tabular-nums text-gray-700">₱{{ number_format($row['vat_itr'], 2) }}</span>
                    @endif
                </td>

                {{-- Net Operating Income --}}
                <td class="px-5 py-3 text-right tabular-nums font-medium">
                    @if($row['is_cost_center'])
                        <span class="text-gray-300">—</span>
                    @else
                        <span :class="net >= 0 ? 'text-green-700' : 'text-red-600'"
                              x-text="'₱' + fmt(net)">
                            <span class="{{ $row['net_operating_income'] >= 0 ? 'text-green-700' : 'text-red-600' }}">
                                ₱{{ number_format($row['net_operating_income'], 2) }}
                            </span>
                        </span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>

        <tfoot class="bg-indigo-50 border-t-2 border-indigo-200 text-sm font-semibold">
            <tr>
                <td class="px-5 py-3 text-gray-700">Total</td>
                <td class="px-5 py-3 text-right tabular-nums text-gray-800">₱{{ number_format($grandSales, 2) }}</td>
                <td class="px-5 py-3 text-right tabular-nums text-gray-800">₱{{ number_format($grandExpenses, 2) }}</td>
                <td class="px-5 py-3 text-right tabular-nums {{ $grandOperating >= 0 ? 'text-green-700' : 'text-red-600' }}">
                    ₱{{ number_format($grandOperating, 2) }}
                </td>
                <td class="px-5 py-3 text-right tabular-nums text-gray-800">₱{{ number_format($grandVatItr, 2) }}</td>
                <td class="px-5 py-3 text-right tabular-nums {{ $grandNet >= 0 ? 'text-green-700' : 'text-red-600' }}">
                    ₱{{ number_format($grandNet, 2) }}
                </td>
            </tr>
            <tr class="text-xs font-normal text-gray-400 border-t border-indigo-100">
                <td colspan="6" class="px-5 py-1.5">
                    Total Sales and VAT/ITR exclude cost center branches. Total Expenses includes all branches.
                    Operating Income = Total Sales &minus; Total Expenses (all branches).
                </td>
            </tr>
        </tfoot>
    </table>
</div>

@if($isSingleMonth)
    <p class="text-xs text-gray-400 mt-3">VAT/ITR is editable per branch for single-month views. Grand totals refresh on next page load.</p>
@else
    <p class="text-xs text-gray-400 mt-3">VAT/ITR shown is the sum of monthly values across the selected range. Filter to a single month to edit.</p>
@endif
@endsection

@push('scripts')
<script>
function fmt(val) {
    return parseFloat(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function applyPreset(preset) {
    const now  = new Date();
    const m    = now.getMonth() + 1;
    const y    = now.getFullYear();
    let fm, fy, tm, ty;

    switch (preset) {
        case 'this_month':
            fm = tm = m; fy = ty = y; break;
        case 'last_3': {
            const d = new Date(y, m - 3, 1);
            fm = d.getMonth() + 1; fy = d.getFullYear(); tm = m; ty = y; break;
        }
        case 'last_6': {
            const d = new Date(y, m - 6, 1);
            fm = d.getMonth() + 1; fy = d.getFullYear(); tm = m; ty = y; break;
        }
        case 'ytd':
            fm = 1; fy = y; tm = m; ty = y; break;
        case 'this_year':
            fm = 1; fy = y; tm = 12; ty = y; break;
        default: return;
    }

    window.location.href = `?from_month=${fm}&from_year=${fy}&to_month=${tm}&to_year=${ty}`;
}
</script>
@endpush
