@extends('layouts.app')
@section('title', $expensePeriod->branch->name . ' — ' . \Carbon\Carbon::create($expensePeriod->year, $expensePeriod->month)->format('F Y'))

@section('content')

<div x-data="expenseApp()" x-init="init()">

{{-- Header --}}
<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-xl font-bold text-gray-800">{{ $expensePeriod->branch->name }}</h1>
        <p class="text-sm text-gray-500">
            {{ \Carbon\Carbon::create($expensePeriod->year, $expensePeriod->month)->format('F Y') }}
        </p>
    </div>
    <div class="flex items-center gap-3">
        <a href="{{ route('expense-periods.edit', $expensePeriod) }}"
           class="text-sm text-gray-600 border border-gray-300 px-3 py-1.5 rounded hover:bg-gray-50">
            Edit Period
        </a>
        <a href="{{ route('expense-periods.index') }}"
           class="text-sm text-gray-500 hover:underline">&larr; All Periods</a>
    </div>
</div>

{{-- Main two-column layout --}}
<div class="flex gap-5 items-start">

    {{-- Left: Expense Sheet --}}
    <div class="flex-1 min-w-0">
        <div class="bg-white rounded shadow border border-gray-100 overflow-hidden">

            {{-- Sheet header: title, search, import toggle --}}
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between gap-3">
                <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide shrink-0">Expense Entries</h2>
                <div class="flex-1 max-w-xs">
                    <input type="text"
                           x-model="search"
                           placeholder="Search category or particular…"
                           class="w-full text-xs border-gray-300 rounded focus:ring-indigo-400 px-2 py-1.5">
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <span x-show="search" class="text-xs text-indigo-600"
                          x-text="filteredEntries.length + ' match' + (filteredEntries.length === 1 ? '' : 'es')"></span>
                    <span x-show="!search" class="text-xs text-gray-400"
                          x-text="entries.length + ' rows'"></span>
                    <button type="button" x-show="search" @click="search = ''"
                            class="text-xs text-gray-400 hover:text-gray-600">&times; Clear</button>
                    <button type="button" @click="showImport = !showImport"
                            class="text-xs px-2 py-1 rounded border border-indigo-300 text-indigo-600 hover:bg-indigo-50"
                            x-text="showImport ? 'Cancel Import' : 'Import JSON'"></button>
                </div>
            </div>

            {{-- JSON Import Panel --}}
            <div x-show="showImport" x-cloak class="border-b border-gray-200 bg-indigo-50 px-4 py-3">
                <p class="text-xs font-semibold text-indigo-700 uppercase mb-2">Import from JSON</p>
                <textarea x-model="jsonInput" rows="6"
                          placeholder='Paste JSON array here, e.g. [{"date":"Feb 1, 2026","category":"Store Supplies","particular":"Gloves","amount":658.00,"notes":""}]'
                          class="w-full text-xs border-gray-300 rounded focus:ring-indigo-400 px-2 py-1.5 font-mono"></textarea>
                <div class="flex items-center gap-3 mt-2">
                    <button type="button" @click="importFromJson()"
                            class="text-xs bg-indigo-600 text-white px-3 py-1.5 rounded hover:bg-indigo-700">
                        Import
                    </button>
                    <span class="text-xs text-gray-500">Categories must match exactly. Empty dates default to the last day of this period's month.</span>
                </div>
                <template x-if="importResult">
                    <div>
                        <p x-show="importResult.error" class="mt-2 text-xs text-red-600 font-medium" x-text="importResult.error"></p>
                        <div x-show="!importResult.error">
                            <p class="mt-2 text-xs text-green-700 font-medium"
                               x-text="importResult.imported + ' ' + (importResult.imported === 1 ? 'entry' : 'entries') + ' imported successfully.'"></p>
                            <p x-show="importResult.skipped && importResult.skipped.length > 0"
                               class="text-xs text-amber-700 mt-1"
                               x-text="'Skipped (unknown categories): ' + (importResult.skipped || []).join(', ')"></p>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Entries table --}}
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                        <tr>
                            <th class="px-3 py-2 text-left w-28">
                                <button type="button" @click="toggleSort()"
                                        class="flex items-center gap-1 hover:text-indigo-600 uppercase tracking-wide">
                                    Date
                                    <span x-text="sortDirection === 'asc' ? '↑' : '↓'"></span>
                                </button>
                            </th>
                            <th class="px-3 py-2 text-left">Category</th>
                            <th class="px-3 py-2 text-left">Particular</th>
                            <th class="px-3 py-2 text-right w-32">Amount</th>
                            <th class="px-3 py-2 text-left">Notes</th>
                            <th class="px-3 py-2 text-right w-36">Running Total</th>
                            <th class="px-3 py-2 w-20"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr x-show="entriesWithRunningTotals.length === 0">
                            <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">
                                No entries yet. Add one below.
                            </td>
                        </tr>
                        <template x-for="entry in entriesWithRunningTotals" :key="entry.id">
                            <tr :class="editingId === entry.id ? 'bg-indigo-50' : 'hover:bg-gray-50 group'">
                                {{-- Date --}}
                                <td class="px-3 py-2">
                                    <span x-show="editingId !== entry.id"
                                          class="text-gray-600 whitespace-nowrap text-xs"
                                          x-text="formatDate(entry.date)"></span>
                                    <input x-show="editingId === entry.id"
                                           type="date" x-model="editForm.date"
                                           class="w-full text-xs border-gray-300 rounded focus:ring-indigo-400 px-1 py-1">
                                </td>
                                {{-- Category --}}
                                <td class="px-3 py-2">
                                    <span x-show="editingId !== entry.id"
                                          class="px-2 py-0.5 rounded text-xs font-medium"
                                          :class="categoryColor(entry.category_name)"
                                          x-text="entry.category_name"></span>
                                    <select x-show="editingId === entry.id"
                                            x-model="editForm.category_id"
                                            class="w-full text-xs border-gray-300 rounded focus:ring-indigo-400 px-1 py-1">
                                        <option value="">Category…</option>
                                        <template x-for="cat in categories" :key="cat.id">
                                            <option :value="cat.id" x-text="cat.name"></option>
                                        </template>
                                    </select>
                                </td>
                                {{-- Particular --}}
                                <td class="px-3 py-2">
                                    <span x-show="editingId !== entry.id"
                                          class="text-gray-800 text-xs"
                                          x-text="entry.particular"></span>
                                    <span x-show="editingId !== entry.id && entry.created_by_name"
                                          class="block text-gray-400 text-xs mt-0.5">
                                        <span x-text="'by ' + entry.created_by_name"></span>
                                        <span x-show="entry.updated_by_name && entry.updated_by_name !== entry.created_by_name"
                                              x-text="' · edited by ' + entry.updated_by_name"></span>
                                    </span>
                                    <input x-show="editingId === entry.id"
                                           type="text" x-model="editForm.particular"
                                           class="w-full text-xs border-gray-300 rounded focus:ring-indigo-400 px-1 py-1">
                                </td>
                                {{-- Amount --}}
                                <td class="px-3 py-2 text-right">
                                    <span x-show="editingId !== entry.id"
                                          class="text-gray-700 tabular-nums text-xs"
                                          x-text="fmt(entry.amount)"></span>
                                    <input x-show="editingId === entry.id"
                                           type="number" x-model="editForm.amount" step="0.01"
                                           class="w-full text-xs border-gray-300 rounded focus:ring-indigo-400 px-1 py-1 text-right">
                                </td>
                                {{-- Notes --}}
                                <td class="px-3 py-2">
                                    <span x-show="editingId !== entry.id"
                                          class="text-gray-500 text-xs"
                                          x-text="entry.notes"></span>
                                    <input x-show="editingId === entry.id"
                                           type="text" x-model="editForm.notes"
                                           class="w-full text-xs border-gray-300 rounded focus:ring-indigo-400 px-1 py-1">
                                </td>
                                {{-- Running Total --}}
                                <td class="px-3 py-2 text-right">
                                    <span x-show="editingId !== entry.id"
                                          class="font-medium text-indigo-700 tabular-nums text-xs"
                                          x-text="fmt(entry.running_total)"></span>
                                    <span x-show="editingId === entry.id"
                                          class="text-gray-400 text-xs">—</span>
                                </td>
                                {{-- Actions --}}
                                <td class="px-3 py-2 text-center">
                                    <span x-show="editingId !== entry.id"
                                          class="opacity-0 group-hover:opacity-100 transition-opacity space-x-1">
                                        <button type="button" @click="startEdit(entry)"
                                                class="text-xs text-indigo-600 hover:underline">Edit</button>
                                        <button type="button" @click="deleteEntry(entry.id)"
                                                class="text-xs text-red-500 hover:underline">Del</button>
                                    </span>
                                    <span x-show="editingId === entry.id" class="space-x-1">
                                        <button type="button" @click="saveEdit()"
                                                class="text-xs bg-indigo-600 text-white px-2 py-0.5 rounded hover:bg-indigo-700">Save</button>
                                        <button type="button" @click="cancelEdit()"
                                                class="text-xs text-gray-500 hover:underline">Cancel</button>
                                    </span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Add Entry Form --}}
            <div class="border-t border-gray-200 bg-gray-50 px-3 py-3">
                <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Add Entry</p>
                <div class="grid grid-cols-12 gap-2 items-start">
                    <div class="col-span-2">
                        <input type="date" x-model="newForm.date"
                               :class="newErrors.date ? 'border-red-400' : 'border-gray-300'"
                               class="w-full text-xs rounded focus:ring-indigo-400 px-2 py-1.5">
                        <span x-show="newErrors.date" class="text-red-500 text-xs" x-text="newErrors.date"></span>
                    </div>
                    <div class="col-span-2">
                        <select x-model="newForm.category_id"
                                :class="newErrors.category_id ? 'border-red-400' : 'border-gray-300'"
                                class="w-full text-xs rounded focus:ring-indigo-400 px-2 py-1.5">
                            <option value="">Category…</option>
                            <template x-for="cat in categories" :key="cat.id">
                                <option :value="cat.id" x-text="cat.name"></option>
                            </template>
                        </select>
                        <span x-show="newErrors.category_id" class="text-red-500 text-xs" x-text="newErrors.category_id"></span>
                    </div>
                    <div class="col-span-3">
                        <input type="text" x-model="newForm.particular" placeholder="Particular"
                               :class="newErrors.particular ? 'border-red-400' : 'border-gray-300'"
                               class="w-full text-xs rounded focus:ring-indigo-400 px-2 py-1.5">
                        <span x-show="newErrors.particular" class="text-red-500 text-xs" x-text="newErrors.particular"></span>
                    </div>
                    <div class="col-span-2">
                        <input type="number" x-model="newForm.amount" step="0.01" placeholder="Amount"
                               :class="newErrors.amount ? 'border-red-400' : 'border-gray-300'"
                               class="w-full text-xs rounded focus:ring-indigo-400 px-2 py-1.5 text-right">
                        <span x-show="newErrors.amount" class="text-red-500 text-xs" x-text="newErrors.amount"></span>
                    </div>
                    <div class="col-span-2">
                        <input type="text" x-model="newForm.notes" placeholder="Notes (optional)"
                               class="w-full text-xs border-gray-300 rounded focus:ring-indigo-400 px-2 py-1.5">
                    </div>
                    <div class="col-span-1 flex items-start pt-0.5">
                        <button type="button" @click="addEntry()"
                                class="w-full bg-indigo-600 text-white text-xs px-3 py-1.5 rounded hover:bg-indigo-700">
                            Add
                        </button>
                    </div>
                </div>
            </div>

        </div>{{-- /expense sheet card --}}
    </div>

    {{-- Right sidebar --}}
    <div class="w-80 shrink-0 space-y-4">

        {{-- Category Summary --}}
        <div class="bg-white rounded shadow border border-gray-100 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Category Summary</h2>
                <span class="text-xs font-mono text-indigo-700 font-medium" x-text="'₱' + fmt(grandTotal)"></span>
            </div>
            <ul class="divide-y divide-gray-50 text-sm max-h-96 overflow-y-auto">
                <template x-if="Object.keys(categoryTotals).length === 0">
                    <li class="px-4 py-6 text-center text-gray-400 text-xs">No entries yet.</li>
                </template>
                <template x-for="[name, total] in Object.entries(categoryTotals)" :key="name">
                    <li class="flex justify-between px-4 py-2 hover:bg-gray-50">
                        <span class="text-gray-700 text-xs leading-snug" x-text="name"></span>
                        <span class="text-gray-800 tabular-nums text-xs font-medium ml-4 shrink-0" x-text="fmt(total)"></span>
                    </li>
                </template>
            </ul>
            <template x-if="Object.keys(categoryTotals).length > 0">
                <div class="px-4 py-2 bg-gray-50 border-t border-gray-100 flex justify-between text-sm font-semibold">
                    <span class="text-gray-600">Total</span>
                    <span class="text-indigo-700 tabular-nums" x-text="'₱' + fmt(grandTotal)"></span>
                </div>
            </template>
        </div>

        {{-- Operating Income --}}
        <div class="bg-white rounded shadow border border-gray-100 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100">
                <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Operating Income</h2>
            </div>

            {{-- Gross Sales Inputs per Branch --}}
            <div class="px-4 py-3 border-b border-gray-100 space-y-2">
                <p class="text-xs font-medium text-gray-500 uppercase mb-2">Gross Sales by Branch</p>
                <template x-for="branch in branches" :key="branch.id">
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-gray-600 w-28 shrink-0" x-text="branch.name"></label>
                        <input type="number"
                               :value="salesInputs[branch.id] || 0"
                               @input="salesInputs[branch.id] = $event.target.value"
                               step="0.01" min="0"
                               class="flex-1 text-xs border-gray-300 rounded px-2 py-1 text-right focus:ring-indigo-400">
                    </div>
                </template>
                <button type="button" @click="saveGrossSales()"
                        class="mt-2 w-full bg-gray-700 text-white text-xs px-3 py-1.5 rounded hover:bg-gray-800">
                    Save Gross Sales
                </button>
            </div>

            {{-- Computed Metrics --}}
            <div class="divide-y divide-gray-100 text-sm">
                <div class="flex justify-between px-4 py-2">
                    <span class="text-gray-600 text-xs">Gross Sales</span>
                    <span class="tabular-nums text-xs font-medium text-gray-800" x-text="'₱' + fmt(grossSalesTotal)"></span>
                </div>
                <div class="flex justify-between px-4 py-2">
                    <span class="text-gray-600 text-xs">Total Expenses</span>
                    <span class="tabular-nums text-xs font-medium text-red-600" x-text="'₱' + fmt(grandTotal)"></span>
                </div>
                <div class="flex justify-between px-4 py-2 bg-gray-50">
                    <span class="text-gray-700 text-xs font-semibold">Operating Income</span>
                    <span class="tabular-nums text-xs font-bold"
                          :class="operatingIncome >= 0 ? 'text-green-700' : 'text-red-700'"
                          x-text="'₱' + fmt(operatingIncome)"></span>
                </div>
                <div class="flex justify-between px-4 py-2">
                    <span class="text-gray-600 text-xs">VAT/ITR Estimate</span>
                    <span class="tabular-nums text-xs font-medium text-orange-600" x-text="'₱' + fmt(vatItrEstimate)"></span>
                </div>
                <div class="flex justify-between px-4 py-3 bg-indigo-50">
                    <span class="text-indigo-800 text-xs font-bold uppercase tracking-wide">Net Operating Income</span>
                    <span class="tabular-nums text-sm font-bold"
                          :class="netOperatingIncome >= 0 ? 'text-indigo-700' : 'text-red-700'"
                          x-text="'₱' + fmt(netOperatingIncome)"></span>
                </div>
            </div>
        </div>

    </div>{{-- /right sidebar --}}

</div>{{-- /main layout --}}

{{-- Month Tab Navigation --}}
@if($monthPeriods->count() > 1)
<div class="mt-6 border-t border-gray-200 pt-4">
    <p class="text-xs text-gray-500 mb-2 uppercase tracking-wide font-medium">
        {{ $expensePeriod->year }} — {{ $expensePeriod->branch->name }}
    </p>
    <div class="flex flex-wrap gap-2">
        @foreach($monthPeriods as $mp)
            <a href="{{ route('expense-periods.show', $mp) }}"
               class="text-sm px-3 py-1.5 rounded border
                   {{ $mp->id === $expensePeriod->id
                       ? 'bg-indigo-600 text-white border-indigo-600'
                       : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' }}">
                {{ \Carbon\Carbon::create($mp->year, $mp->month)->format('M') }}
            </a>
        @endforeach
    </div>
</div>
@endif

</div>{{-- /x-data --}}

@endsection

@push('scripts')
<script>
// Category color map — keep as literal strings so Tailwind scans them
const CATEGORY_COLORS = {
    'Staff Payroll and Allowance':          'bg-blue-100 text-blue-700',
    'SSS Employer Share':                   'bg-blue-100 text-blue-700',
    'Pag-ibig Employer Share':              'bg-blue-100 text-blue-700',
    'PHIC Employer Share':                  'bg-blue-100 text-blue-700',
    'Store Rental & CUSA':                  'bg-amber-100 text-amber-700',
    'Store Maintenance':                    'bg-amber-100 text-amber-700',
    'Equipment Maintenance':                'bg-orange-100 text-orange-700',
    'Pest Control':                         'bg-orange-100 text-orange-700',
    'Hydro Lab':                            'bg-orange-100 text-orange-700',
    'Store Supplies':                       'bg-green-100 text-green-700',
    'Representations':                      'bg-purple-100 text-purple-700',
    'Stocks Cost':                          'bg-purple-100 text-purple-700',
    "BIR & City Gov't Fees":               'bg-red-100 text-red-700',
    'Royalty Fee':                          'bg-red-100 text-red-700',
    'Ads Fee':                              'bg-red-100 text-red-700',
    "Retainer's Fee":                       'bg-red-100 text-red-700',
    'Cashless Fees':                        'bg-red-100 text-red-700',
    'Unreleased 13th Month':               'bg-teal-100 text-teal-700',
    'Released 13th Month & SIL':           'bg-teal-100 text-teal-700',
    'Service Incentive Leave(SIL)':        'bg-teal-100 text-teal-700',
    'Unreleased Separation/Retirement Pay':'bg-teal-100 text-teal-700',
    'Released Separation/Retirement Pay':  'bg-teal-100 text-teal-700',
    'Ins., Renewals and Other Fees':       'bg-yellow-100 text-yellow-700',
    'Tel, Cable, Internet & Cel.':         'bg-sky-100 text-sky-700',
    'Other Expense':                        'bg-gray-100 text-gray-600',
    'Miscellaneous':                        'bg-gray-100 text-gray-600',
};

function expenseApp() {
    return {
        // ── Static data from server ──────────────────────────────────────────
        periodId:       @json($expensePeriod->id),
        periodYear:     @json($expensePeriod->year),
        periodMonth:    @json($expensePeriod->month),
        vatItrEstimate: @json((float) $expensePeriod->vat_itr_estimate),
        categories:     @json($categories),
        branches:       @json($branches),

        // ── Entries ──────────────────────────────────────────────────────────
        entries: @json($entries),

        // ── Sort & search ────────────────────────────────────────────────────
        sortDirection: 'desc',
        search: '',

        // ── Add-entry form ───────────────────────────────────────────────────
        newForm: {
            date: '',
            category_id: '',
            particular: '',
            amount: '',
            notes: '',
        },
        newErrors: {},

        // ── Inline edit ──────────────────────────────────────────────────────
        editingId: null,
        editForm: {
            date: '',
            category_id: '',
            particular: '',
            amount: '',
            notes: '',
        },

        // ── JSON import ──────────────────────────────────────────────────────
        showImport: false,
        jsonInput: '',
        importResult: null,

        // ── Gross sales ──────────────────────────────────────────────────────
        salesInputs: {},

        // ── Lifecycle ────────────────────────────────────────────────────────
        init() {
            // Default new-entry date to today
            const today = new Date();
            this.newForm.date = today.getFullYear() + '-'
                + String(today.getMonth() + 1).padStart(2, '0') + '-'
                + String(today.getDate()).padStart(2, '0');

            // Initialise salesInputs for every branch (default 0)
            const loaded = @json($grossSales);
            for (const branch of this.branches) {
                this.salesInputs[branch.id] = loaded[branch.id] !== undefined
                    ? parseFloat(loaded[branch.id])
                    : 0;
            }
        },

        // ── Computed: sorted + filtered entries ──────────────────────────────
        get sortedEntries() {
            return [...this.entries].sort((a, b) => {
                if (a.date !== b.date) {
                    return this.sortDirection === 'asc'
                        ? (a.date < b.date ? -1 : 1)
                        : (a.date > b.date ? -1 : 1);
                }
                return a.sort_order - b.sort_order;
            });
        },

        get filteredEntries() {
            if (!this.search.trim()) return this.sortedEntries;
            const q = this.search.toLowerCase();
            return this.sortedEntries.filter(e =>
                e.category_name.toLowerCase().includes(q) ||
                e.particular.toLowerCase().includes(q)
            );
        },

        get entriesWithRunningTotals() {
            let running = 0;
            return this.filteredEntries.map(e => ({
                ...e,
                running_total: (running += parseFloat(e.amount) || 0, running),
            }));
        },

        // ── Computed: sidebar panels ─────────────────────────────────────────
        get categoryTotals() {
            const totals = {};
            for (const e of this.entries) {
                totals[e.category_name] = (totals[e.category_name] || 0) + (parseFloat(e.amount) || 0);
            }
            return Object.fromEntries(
                Object.entries(totals).sort(([a], [b]) => a.localeCompare(b))
            );
        },

        get grandTotal() {
            return this.entries.reduce((sum, e) => sum + (parseFloat(e.amount) || 0), 0);
        },

        get grossSalesTotal() {
            return Object.values(this.salesInputs)
                .reduce((sum, v) => sum + (parseFloat(v) || 0), 0);
        },

        get operatingIncome() {
            return this.grossSalesTotal - this.grandTotal;
        },

        get netOperatingIncome() {
            return this.operatingIncome - this.vatItrEstimate;
        },

        // ── Helpers ──────────────────────────────────────────────────────────
        toggleSort() {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        },

        formatDate(dateStr) {
            // dateStr is "YYYY-MM-DD"; avoid UTC offset shifting by parsing as local
            const [y, m, d] = dateStr.split('-').map(Number);
            return new Date(y, m - 1, d).toLocaleDateString('en-US', {
                month: 'short', day: '2-digit', year: 'numeric',
            });
        },

        fmt(val) {
            return parseFloat(val || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },

        categoryColor(name) {
            return CATEGORY_COLORS[name] || 'bg-gray-100 text-gray-600';
        },

        // ── API helper ───────────────────────────────────────────────────────
        async api(method, url, body = null) {
            const opts = {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            };
            if (body !== null) opts.body = JSON.stringify(body);
            const res = await fetch(url, opts);
            return res.json();
        },

        // ── Entry mutations ──────────────────────────────────────────────────
        async addEntry() {
            this.newErrors = {};
            if (!this.newForm.date)        this.newErrors.date        = 'Required';
            if (!this.newForm.category_id) this.newErrors.category_id = 'Required';
            if (!this.newForm.particular)  this.newErrors.particular  = 'Required';
            if (!this.newForm.amount || isNaN(parseFloat(this.newForm.amount)))
                                           this.newErrors.amount      = 'Required';
            if (Object.keys(this.newErrors).length) return;

            const maxOrder = this.entries.reduce((m, e) => Math.max(m, e.sort_order || 0), 0);

            const data = await this.api('POST', '/expense-entries', {
                period_id:   this.periodId,
                date:        this.newForm.date,
                category_id: parseInt(this.newForm.category_id),
                particular:  this.newForm.particular,
                amount:      parseFloat(this.newForm.amount),
                notes:       this.newForm.notes || null,
                sort_order:  maxOrder + 1,
            });

            if (data && data.id) {
                this.entries.push({
                    id:               data.id,
                    date:             data.date.slice(0, 10),
                    category_id:      data.category_id,
                    category_name:    data.category.name,
                    particular:       data.particular,
                    amount:           parseFloat(data.amount),
                    notes:            data.notes || '',
                    sort_order:       data.sort_order || maxOrder + 1,
                    created_by_name:  data.creator?.name || null,
                    updated_by_name:  data.updater?.name || null,
                });
                this.newForm.particular = '';
                this.newForm.amount     = '';
                this.newForm.notes      = '';
            }
        },

        startEdit(entry) {
            this.editingId = entry.id;
            this.editForm = {
                date:        entry.date,
                category_id: entry.category_id,
                particular:  entry.particular,
                amount:      entry.amount,
                notes:       entry.notes || '',
            };
        },

        async saveEdit() {
            const data = await this.api('PUT', `/expense-entries/${this.editingId}`, {
                date:        this.editForm.date,
                category_id: parseInt(this.editForm.category_id),
                particular:  this.editForm.particular,
                amount:      parseFloat(this.editForm.amount),
                notes:       this.editForm.notes || null,
            });

            if (data && data.id) {
                const idx = this.entries.findIndex(e => e.id === this.editingId);
                if (idx !== -1) {
                    this.entries[idx] = {
                        ...this.entries[idx],
                        date:             data.date.slice(0, 10),
                        category_id:      data.category_id,
                        category_name:    data.category.name,
                        particular:       data.particular,
                        amount:           parseFloat(data.amount),
                        notes:            data.notes || '',
                        updated_by_name:  data.updater?.name || null,
                    };
                }
                this.editingId = null;
            }
        },

        cancelEdit() {
            this.editingId = null;
        },

        async deleteEntry(id) {
            if (!confirm('Delete this entry?')) return;
            const data = await this.api('DELETE', `/expense-entries/${id}`);
            if (data && data.deleted) {
                this.entries = this.entries.filter(e => e.id !== id);
            }
        },

        // ── Gross sales save ─────────────────────────────────────────────────
        async saveGrossSales() {
            for (const [branchId, amount] of Object.entries(this.salesInputs)) {
                await this.api('POST', '/gross-sales', {
                    period_id: this.periodId,
                    branch_id: parseInt(branchId),
                    amount:    parseFloat(amount) || 0,
                });
            }
        },

        // ── JSON import ──────────────────────────────────────────────────────
        async importFromJson() {
            this.importResult = null;

            let rows;
            try {
                rows = JSON.parse(this.jsonInput);
                if (!Array.isArray(rows)) throw new Error();
            } catch {
                this.importResult = { error: 'Invalid JSON. Please paste a valid JSON array.' };
                return;
            }

            // Build category lookup (case-insensitive)
            const catMap = {};
            for (const cat of this.categories) {
                catMap[cat.name.toLowerCase().trim()] = cat;
            }

            // Default date: last day of the period's month
            const lastDay = new Date(this.periodYear, this.periodMonth, 0);
            const defaultDate = lastDay.getFullYear() + '-'
                + String(lastDay.getMonth() + 1).padStart(2, '0') + '-'
                + String(lastDay.getDate()).padStart(2, '0');

            let imported = 0;
            const skipped = [];

            for (const row of rows) {
                const catName = (row.category || '').trim();
                const cat = catMap[catName.toLowerCase()];
                if (!cat) {
                    skipped.push(catName || '(empty)');
                    continue;
                }

                let date = defaultDate;
                if (row.date && String(row.date).trim()) {
                    const parsed = new Date(row.date);
                    if (!isNaN(parsed)) {
                        date = parsed.getFullYear() + '-'
                            + String(parsed.getMonth() + 1).padStart(2, '0') + '-'
                            + String(parsed.getDate()).padStart(2, '0');
                    }
                }

                const maxOrder = this.entries.reduce((m, e) => Math.max(m, e.sort_order || 0), 0);

                const data = await this.api('POST', '/expense-entries', {
                    period_id:   this.periodId,
                    date,
                    category_id: cat.id,
                    particular:  (row.particular || '').trim(),
                    amount:      parseFloat(row.amount) || 0,
                    notes:       (row.notes || '').trim() || null,
                    sort_order:  maxOrder + 1,
                });

                if (data && data.id) {
                    this.entries.push({
                        id:               data.id,
                        date:             data.date.slice(0, 10),
                        category_id:      data.category_id,
                        category_name:    data.category.name,
                        particular:       data.particular,
                        amount:           parseFloat(data.amount),
                        notes:            data.notes || '',
                        sort_order:       data.sort_order || maxOrder + 1,
                        created_by_name:  data.creator?.name || null,
                        updated_by_name:  data.updater?.name || null,
                    });
                    imported++;
                }
            }

            this.importResult = {
                imported,
                skipped: [...new Set(skipped)],
            };
            this.jsonInput = '';
        },
    };
}
</script>
@endpush
