@extends('layouts.app')

@section('title', 'Analytics')

@section('content')
<div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Analytics</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $selectedDate->format('F Y') }} — Revenue branches only (except Passbooks & Expenses)</p>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <select name="month" onchange="this.form.submit()"
                    class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @foreach($monthOptions as $opt)
                    <option value="{{ $opt['month'] }}" data-year="{{ $opt['year'] }}"
                            @selected($opt['month'] == $month && $opt['year'] == $year)>
                        {{ $opt['label'] }}
                    </option>
                @endforeach
            </select>
            <input type="hidden" name="year" id="yearField" value="{{ $year }}">
        </form>
    </div>

    {{-- Row 1: 12-month Revenue vs Expenses Trend (full width) --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Revenue vs Expenses — 12-Month Trend</h2>
        <div class="relative h-72">
            <canvas id="trendChart"></canvas>
        </div>
    </div>

    {{-- Row 2: NOI by Branch | Branch MoM Comparison --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-1">Net Operating Income by Branch</h2>
            <p class="text-xs text-gray-400 mb-4">{{ $selectedDate->format('F Y') }} — Sales, Expenses &amp; NOI</p>
            <div class="relative h-64">
                <canvas id="noiChart"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-1">Branch Sales — Month-over-Month</h2>
            <p class="text-xs text-gray-400 mb-4">
                {{ $selectedDate->format('F Y') }} vs {{ $prevDate->format('F Y') }}
            </p>
            <div class="relative h-64">
                <canvas id="momChart"></canvas>
            </div>
        </div>

    </div>

    {{-- Row 3: Top Categories | Op vs Overhead --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-1">Top 10 Expense Categories</h2>
            <p class="text-xs text-gray-400 mb-4">{{ $selectedDate->format('F Y') }} — All branches</p>
            <div class="relative h-72">
                <canvas id="topCatChart"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-1">Operational vs Overhead Split</h2>
            <p class="text-xs text-gray-400 mb-4">{{ $selectedDate->format('F Y') }} — Revenue branches</p>
            <div class="relative h-72">
                <canvas id="splitChart"></canvas>
            </div>
        </div>

    </div>

    {{-- Row 4: Passbook Balances | Daily Sales --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-1">Passbook Balances</h2>
            <p class="text-xs text-gray-400 mb-4">Current running balance — all accounts</p>
            <div class="relative h-64">
                <canvas id="passbookChart"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-1">Daily Sales</h2>
            <p class="text-xs text-gray-400 mb-4">{{ $selectedDate->format('F Y') }} — All revenue branches combined</p>
            <div class="relative h-64">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>

    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
// ── Sync year field with month dropdown ──────────────────────────────────────
document.querySelector('select[name=month]').addEventListener('change', function () {
    const selected = this.options[this.selectedIndex];
    document.getElementById('yearField').value = selected.dataset.year;
});

// ── PHP data ─────────────────────────────────────────────────────────────────
const trendData       = @json($trend);
const branchNoiData   = @json($branchNoi);
const branchMomData   = @json($branchMom);
const topCatData      = @json($topCategories);
const splitData       = @json($branchExpenseSplit);
const passbookData    = @json($passbookBalances);
const dailySalesData  = @json($dailySales);

// ── Formatters ───────────────────────────────────────────────────────────────
const peso = val => '₱' + Number(val).toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

const defaultTooltip = (axis = 'y') => ({
    callbacks: {
        label: ctx => ' ' + peso(axis === 'y' ? ctx.parsed.y : ctx.parsed.x),
    },
});

const baseOptions = (axis = 'y') => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
        tooltip: defaultTooltip(axis),
    },
    scales: {
        x: { grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 11 } } },
        y: { grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 11 }, callback: v => peso(v) } },
    },
});

// ── Colors ───────────────────────────────────────────────────────────────────
const C = {
    indigo:  'rgb(99, 102, 241)',
    indigoA: 'rgba(99, 102, 241, 0.15)',
    rose:    'rgb(244, 63, 94)',
    roseA:   'rgba(244, 63, 94, 0.15)',
    emerald: 'rgb(16, 185, 129)',
    amber:   'rgb(251, 146, 60)',
    blue:    'rgb(59, 130, 246)',
    slate:   'rgb(148, 163, 184)',
    red:     'rgb(239, 68, 68)',
};

// ── Chart 1: 12-month Trend ──────────────────────────────────────────────────
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: trendData.map(d => d.label),
        datasets: [
            {
                label: 'Sales',
                data: trendData.map(d => d.sales),
                borderColor: C.indigo,
                backgroundColor: C.indigoA,
                fill: true,
                tension: 0.3,
                pointRadius: 3,
            },
            {
                label: 'Expenses',
                data: trendData.map(d => d.expenses),
                borderColor: C.rose,
                backgroundColor: C.roseA,
                fill: true,
                tension: 0.3,
                pointRadius: 3,
            },
        ],
    },
    options: {
        ...baseOptions(),
        plugins: {
            ...baseOptions().plugins,
            tooltip: {
                callbacks: {
                    label: ctx => ' ' + ctx.dataset.label + ': ' + peso(ctx.parsed.y),
                },
            },
        },
    },
});

// ── Chart 2: NOI by Branch ───────────────────────────────────────────────────
new Chart(document.getElementById('noiChart'), {
    type: 'bar',
    data: {
        labels: branchNoiData.map(d => d.branch),
        datasets: [
            {
                label: 'Sales',
                data: branchNoiData.map(d => d.sales),
                backgroundColor: C.indigo,
                borderRadius: 3,
            },
            {
                label: 'Expenses',
                data: branchNoiData.map(d => d.expenses),
                backgroundColor: C.rose,
                borderRadius: 3,
            },
            {
                label: 'NOI',
                data: branchNoiData.map(d => d.noi),
                backgroundColor: branchNoiData.map(d => d.noi >= 0 ? C.emerald : C.red),
                borderRadius: 3,
            },
        ],
    },
    options: {
        ...baseOptions(),
        plugins: {
            ...baseOptions().plugins,
            tooltip: {
                callbacks: {
                    label: ctx => ' ' + ctx.dataset.label + ': ' + peso(ctx.parsed.y),
                },
            },
        },
    },
});

// ── Chart 3: Branch MoM Comparison ──────────────────────────────────────────
new Chart(document.getElementById('momChart'), {
    type: 'bar',
    data: {
        labels: branchMomData.map(d => d.branch),
        datasets: [
            {
                label: '{{ $selectedDate->format("M Y") }}',
                data: branchMomData.map(d => d.current),
                backgroundColor: C.indigo,
                borderRadius: 3,
            },
            {
                label: '{{ $prevDate->format("M Y") }}',
                data: branchMomData.map(d => d.previous),
                backgroundColor: C.slate,
                borderRadius: 3,
            },
        ],
    },
    options: {
        ...baseOptions(),
        plugins: {
            ...baseOptions().plugins,
            tooltip: {
                callbacks: {
                    label: ctx => ' ' + ctx.dataset.label + ': ' + peso(ctx.parsed.y),
                },
            },
        },
    },
});

// ── Chart 4: Top 10 Expense Categories (horizontal bar) ─────────────────────
new Chart(document.getElementById('topCatChart'), {
    type: 'bar',
    data: {
        labels: topCatData.map(d => d.name),
        datasets: [{
            label: 'Total Expenses',
            data: topCatData.map(d => d.total),
            backgroundColor: C.rose,
            borderRadius: 3,
        }],
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: defaultTooltip('x'),
        },
        scales: {
            x: {
                grid: { color: 'rgba(0,0,0,0.04)' },
                ticks: { font: { size: 11 }, callback: v => peso(v) },
            },
            y: {
                grid: { display: false },
                ticks: { font: { size: 11 } },
            },
        },
    },
});

// ── Chart 5: Operational vs Overhead Split (stacked) ─────────────────────────
new Chart(document.getElementById('splitChart'), {
    type: 'bar',
    data: {
        labels: splitData.map(d => d.branch),
        datasets: [
            {
                label: 'Operational',
                data: splitData.map(d => d.operational),
                backgroundColor: C.blue,
                borderRadius: 3,
            },
            {
                label: 'Overhead',
                data: splitData.map(d => d.overhead),
                backgroundColor: C.amber,
                borderRadius: 3,
            },
        ],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
            tooltip: {
                callbacks: {
                    label: ctx => ' ' + ctx.dataset.label + ': ' + peso(ctx.parsed.y),
                },
            },
        },
        scales: {
            x: { stacked: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 11 } } },
            y: { stacked: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 11 }, callback: v => peso(v) } },
        },
    },
});

// ── Chart 6: Passbook Balances (horizontal bar) ──────────────────────────────
new Chart(document.getElementById('passbookChart'), {
    type: 'bar',
    data: {
        labels: passbookData.map(d => d.label),
        datasets: [{
            label: 'Balance',
            data: passbookData.map(d => d.balance),
            backgroundColor: passbookData.map(d => d.balance >= 0 ? C.emerald : C.red),
            borderRadius: 3,
        }],
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: defaultTooltip('x'),
        },
        scales: {
            x: {
                grid: { color: 'rgba(0,0,0,0.04)' },
                ticks: { font: { size: 11 }, callback: v => peso(v) },
            },
            y: {
                grid: { display: false },
                ticks: { font: { size: 11 } },
            },
        },
    },
});

// ── Chart 7: Daily Sales ─────────────────────────────────────────────────────
new Chart(document.getElementById('dailyChart'), {
    type: 'bar',
    data: {
        labels: dailySalesData.map(d => d.date),
        datasets: [{
            label: 'Daily Sales',
            data: dailySalesData.map(d => d.total),
            backgroundColor: C.indigo,
            borderRadius: 3,
        }],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: defaultTooltip('y'),
        },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 10 }, maxRotation: 45 } },
            y: { grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 11 }, callback: v => peso(v) } },
        },
    },
});
</script>
@endpush
@endsection
