@extends('layouts.app')
@section('title', $period->branch->name . ' — ' . $period->month_name . ' Sales')

@section('content')
<div x-data="salesApp()" x-init="init()">

    {{-- Header --}}
    <div class="flex items-start justify-between mb-6">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('sales.index') }}" class="hover:underline">Sales</a>
                <span>/</span>
                <span>{{ $period->branch->name }}</span>
                <span>/</span>
                <span>{{ $period->month_name }}</span>
            </div>
            <h1 class="text-xl font-bold text-gray-800">
                {{ $period->branch->name }} &mdash; {{ $period->month_name }}
            </h1>
        </div>

        {{-- Total card --}}
        <div class="text-right bg-emerald-50 border border-emerald-200 rounded-lg px-6 py-3">
            <p class="text-xs text-emerald-600 font-medium uppercase tracking-wide">Total Gross Sales</p>
            <p class="text-2xl font-bold text-emerald-700 tabular-nums" x-text="'₱' + format(totalSales)"></p>
            <p class="text-xs text-emerald-500 mt-0.5" x-text="entries.length + ' day(s) encoded'"></p>
        </div>
    </div>

    {{-- Add Entry Form --}}
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm mb-6 p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Add Daily Sales Entry</h2>
        <div class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Date <span class="text-red-400">*</span></label>
                <input type="date" x-model="form.date"
                       class="border-gray-300 rounded text-sm focus:ring-indigo-500 focus:border-indigo-500"
                       :max="maxDate">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Gross Sales <span class="text-red-400">*</span></label>
                <input type="number" x-model="form.amount" min="0" step="0.01" placeholder="0.00"
                       class="border-gray-300 rounded text-sm focus:ring-indigo-500 focus:border-indigo-500 w-48"
                       @keydown.enter="addEntry">
            </div>
            <div class="flex-1 min-w-48">
                <label class="block text-xs text-gray-500 mb-1">Notes <span class="text-gray-300">(optional)</span></label>
                <input type="text" x-model="form.notes" placeholder="e.g. Holiday, promo day…"
                       class="w-full border-gray-300 rounded text-sm focus:ring-indigo-500 focus:border-indigo-500"
                       @keydown.enter="addEntry">
            </div>
            <div class="flex gap-2">
                <button @click="addEntry" :disabled="saving"
                        class="bg-indigo-600 text-white text-sm px-5 py-2 rounded hover:bg-indigo-700 disabled:opacity-50">
                    <span x-show="!saving">Add Entry</span>
                    <span x-show="saving">Saving…</span>
                </button>
            </div>
        </div>
        <p x-show="formError" x-text="formError" class="text-red-500 text-xs mt-2"></p>
    </div>

    {{-- Entries Table --}}
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-right">Gross Sales</th>
                    <th class="px-4 py-3 text-left">Notes</th>
                    <th class="px-4 py-3 text-left">Encoded By</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <template x-for="entry in sortedEntries" :key="entry.id">
                    <tr class="hover:bg-gray-50">

                        {{-- View mode --}}
                        <template x-if="editingId !== entry.id">
                            <td class="px-4 py-3 font-medium text-gray-700 tabular-nums" x-text="formatDate(entry.date)"></td>
                        </template>
                        <template x-if="editingId !== entry.id">
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-emerald-700"
                                x-text="'₱' + format(entry.amount)"></td>
                        </template>
                        <template x-if="editingId !== entry.id">
                            <td class="px-4 py-3 text-gray-500" x-text="entry.notes || '—'"></td>
                        </template>
                        <template x-if="editingId !== entry.id">
                            <td class="px-4 py-3 text-xs text-gray-400">
                                <span x-text="entry.created_by_name ? 'by ' + entry.created_by_name : ''"></span>
                                <template x-if="entry.updated_by_name && entry.updated_by_name !== entry.created_by_name">
                                    <span x-text="' · edited by ' + entry.updated_by_name" class="text-gray-400"></span>
                                </template>
                            </td>
                        </template>
                        <template x-if="editingId !== entry.id">
                            <td class="px-4 py-3 text-center space-x-3">
                                <button @click="startEdit(entry)" class="text-indigo-600 hover:underline text-xs">Edit</button>
                                <button @click="deleteEntry(entry.id)" class="text-red-500 hover:underline text-xs">Delete</button>
                            </td>
                        </template>

                        {{-- Edit mode --}}
                        <template x-if="editingId === entry.id">
                            <td class="px-4 py-2">
                                <input type="date" x-model="editForm.date"
                                       class="border-gray-300 rounded text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </td>
                        </template>
                        <template x-if="editingId === entry.id">
                            <td class="px-4 py-2">
                                <input type="number" x-model="editForm.amount" min="0" step="0.01"
                                       class="border-gray-300 rounded text-sm focus:ring-indigo-500 focus:border-indigo-500 w-40 text-right">
                            </td>
                        </template>
                        <template x-if="editingId === entry.id">
                            <td class="px-4 py-2" colspan="2">
                                <input type="text" x-model="editForm.notes" placeholder="Notes"
                                       class="w-full border-gray-300 rounded text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </td>
                        </template>
                        <template x-if="editingId === entry.id">
                            <td class="px-4 py-2 text-center space-x-2">
                                <button @click="saveEdit(entry)" class="text-emerald-600 hover:underline text-xs font-medium">Save</button>
                                <button @click="cancelEdit()" class="text-gray-400 hover:underline text-xs">Cancel</button>
                            </td>
                        </template>
                    </tr>
                </template>

                {{-- Empty state --}}
                <tr x-show="entries.length === 0">
                    <td colspan="5" class="px-4 py-12 text-center text-gray-400">
                        <div class="text-3xl mb-2">📊</div>
                        <p class="font-medium">No sales entries yet</p>
                        <p class="text-xs mt-1">Use the form above to add the first entry for this period.</p>
                    </td>
                </tr>
            </tbody>

            {{-- Footer total --}}
            <tfoot x-show="entries.length > 0" class="bg-emerald-50 border-t-2 border-emerald-200">
                <tr>
                    <td class="px-4 py-3 font-semibold text-gray-700 text-sm">
                        Total (<span x-text="entries.length"></span> days)
                    </td>
                    <td class="px-4 py-3 text-right font-bold text-emerald-700 tabular-nums text-base"
                        x-text="'₱' + format(totalSales)"></td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
    </div>

</div>
@endsection

@push('scripts')
<script>
function salesApp() {
    return {
        entries: @json($entries),
        periodId: {{ $period->id }},
        saving: false,
        editingId: null,
        formError: '',

        form: { date: '', amount: '', notes: '' },
        editForm: { date: '', amount: '', notes: '' },

        get maxDate() {
            // Restrict date picker to the period's month/year
            const year  = {{ $period->year }};
            const month = {{ $period->month }};
            const last  = new Date(year, month, 0).getDate();
            return `${year}-${String(month).padStart(2, '0')}-${String(last).padStart(2, '0')}`;
        },

        get minDate() {
            const year  = {{ $period->year }};
            const month = {{ $period->month }};
            return `${year}-${String(month).padStart(2, '0')}-01`;
        },

        get totalSales() {
            return this.entries.reduce((sum, e) => sum + parseFloat(e.amount || 0), 0);
        },

        get sortedEntries() {
            return [...this.entries].sort((a, b) => a.date.localeCompare(b.date));
        },

        init() {
            // Default add-form date to today if within period, else first of month
            const year  = {{ $period->year }};
            const month = {{ $period->month }};
            const today = new Date();
            if (today.getFullYear() === year && today.getMonth() + 1 === month) {
                this.form.date = today.toISOString().slice(0, 10);
            } else {
                this.form.date = this.minDate;
            }
        },

        async addEntry() {
            this.formError = '';
            if (!this.form.date || !this.form.amount) {
                this.formError = 'Date and Gross Sales are required.';
                return;
            }
            // Validate date is within this period
            if (this.form.date < this.minDate || this.form.date > this.maxDate) {
                this.formError = `Date must be within {{ $period->month_name }}.`;
                return;
            }
            // Check for duplicate date
            if (this.entries.find(e => e.date === this.form.date)) {
                this.formError = 'An entry for this date already exists. Edit the existing row instead.';
                return;
            }
            this.saving = true;
            try {
                const res = await this.api('POST', '/sales-entries', {
                    period_id: this.periodId,
                    date:      this.form.date,
                    amount:    this.form.amount,
                    notes:     this.form.notes,
                });
                this.entries.push(res);
                this.form.amount = '';
                this.form.notes  = '';
                // Advance date by 1 day for convenience
                const next = new Date(this.form.date);
                next.setDate(next.getDate() + 1);
                const nextStr = next.toISOString().slice(0, 10);
                this.form.date = nextStr <= this.maxDate ? nextStr : this.form.date;
            } catch (e) {
                this.formError = e.message || 'Failed to save entry.';
            }
            this.saving = false;
        },

        startEdit(entry) {
            this.editingId = entry.id;
            this.editForm  = { date: entry.date, amount: entry.amount, notes: entry.notes };
        },

        cancelEdit() {
            this.editingId = null;
        },

        async saveEdit(entry) {
            if (!this.editForm.date || !this.editForm.amount) return;
            try {
                const res = await this.api('PUT', `/sales-entries/${entry.id}`, this.editForm);
                const idx = this.entries.findIndex(e => e.id === entry.id);
                if (idx !== -1) this.entries[idx] = res;
                this.editingId = null;
            } catch (e) {
                alert(e.message || 'Failed to update entry.');
            }
        },

        async deleteEntry(id) {
            if (!confirm('Delete this sales entry?')) return;
            try {
                await this.api('DELETE', `/sales-entries/${id}`);
                this.entries = this.entries.filter(e => e.id !== id);
            } catch (e) {
                alert(e.message || 'Failed to delete entry.');
            }
        },

        format(value) {
            return parseFloat(value || 0).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },

        formatDate(dateStr) {
            const d = new Date(dateStr + 'T00:00:00');
            return d.toLocaleDateString('en-PH', { month: 'short', day: '2-digit', weekday: 'short' });
        },

        async api(method, url, body = null) {
            const opts = {
                method,
                headers: {
                    'Content-Type':  'application/json',
                    'Accept':        'application/json',
                    'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]').content,
                },
            };
            if (body) opts.body = JSON.stringify(body);
            const res  = await fetch(url, opts);
            const json = await res.json();
            if (!res.ok) {
                const msg = json.message || (json.errors ? Object.values(json.errors).flat().join(' ') : 'Error');
                throw new Error(msg);
            }
            return json;
        },
    };
}
</script>
@endpush
