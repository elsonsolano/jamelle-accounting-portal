@extends('layouts.app')

@section('title', 'Deposit Slip Submissions')

@section('content')
<div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Deposit Slip Submissions</h1>
            <p class="text-sm text-gray-500 mt-0.5">Deposit slips submitted via Messenger Bot</p>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <select name="status" onchange="this.form.submit()"
                    class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="" @selected(!request('status'))>All</option>
                <option value="success" @selected(request('status') === 'success')>Parsed</option>
                <option value="low_confidence" @selected(request('status') === 'low_confidence')>Low Confidence</option>
                <option value="failed" @selected(request('status') === 'failed')>Parse Failed</option>
                <option value="duplicate" @selected(request('status') === 'duplicate')>Duplicate</option>
                <option value="unreviewed" @selected(request('status') === 'unreviewed')>Pending Review</option>
            </select>
        </form>
    </div>

    {{-- Submissions table --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm" x-data="depositSlips()">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Staff</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Bank / Account</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Deposit Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Reference</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Parse</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Admin</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Passbook</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Submitted</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($submissions as $sub)
                    <tr class="hover:bg-gray-50 {{ $sub->isRejected() ? 'opacity-50' : '' }}">

                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $sub->staff?->fb_name ?? '—' }}</div>
                            <div class="text-xs text-gray-500">{{ $sub->staff?->employee_code ?? $sub->fb_sender_id }}</div>
                            <div class="text-xs text-gray-400">{{ $sub->branch?->name ?? '' }}</div>
                        </td>

                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $sub->bank_name ?? '—' }}</div>
                            <div class="text-xs text-gray-500 font-mono">{{ $sub->account_number ?? '' }}</div>
                        </td>

                        <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900">
                            @if($sub->amount !== null)
                                ₱{{ number_format($sub->amount, 2) }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-gray-700 text-xs">
                            {{ $sub->deposit_date?->format('M d, Y') ?? '—' }}
                        </td>

                        <td class="px-4 py-3 font-mono text-xs text-gray-700">
                            {{ $sub->reference_number ?? '—' }}
                        </td>

                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $sub->parseBadgeClass() }}">
                                {{ $sub->parseStatusLabel() }}
                            </span>
                            @if($sub->confidence_notes)
                                <div class="text-xs text-gray-400 italic mt-0.5">{{ $sub->confidence_notes }}</div>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $sub->adminBadgeClass() }}">
                                {{ $sub->adminStatusLabel() }}
                            </span>
                        </td>

                        <td class="px-4 py-3 text-xs">
                            @if($sub->passbook)
                                <a href="{{ route('passbooks.show', $sub->passbook_id) }}"
                                   class="text-indigo-600 hover:underline">
                                    {{ $sub->passbook->label() }}
                                </a>
                                @if($sub->passbook->branch)
                                    <div class="text-gray-400 mt-0.5">{{ $sub->passbook->branch->name }}</div>
                                @endif
                            @else
                                <span class="text-gray-400">No match</span>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-xs text-gray-500">
                            {{ $sub->created_at->format('M d, Y H:i') }}
                        </td>

                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2 flex-wrap">

                                {{-- View image --}}
                                @if($sub->image_path)
                                    <a href="{{ route('deposit-slips.image', $sub) }}" target="_blank"
                                       class="text-xs text-indigo-600 hover:underline whitespace-nowrap">
                                        View Slip
                                    </a>
                                @endif

                                @if(!$sub->isRejected())
                                    {{-- Edit button --}}
                                    <button type="button"
                                            @click="openEdit({{ $sub->id }}, {{ json_encode([
                                                'bank_name'        => $sub->bank_name,
                                                'account_number'   => $sub->account_number,
                                                'amount'           => $sub->amount,
                                                'deposit_date'     => $sub->deposit_date?->format('Y-m-d'),
                                                'reference_number' => $sub->reference_number,
                                                'passbook_id'      => $sub->passbook_id,
                                            ]) }})"
                                            class="text-xs text-amber-600 hover:underline whitespace-nowrap">
                                        Edit
                                    </button>

                                    {{-- Reject button --}}
                                    <form method="POST" action="{{ route('deposit-slips.reject', $sub) }}"
                                          onsubmit="return confirm('Reject this submission? The passbook entry will be removed if one was created.')">
                                        @csrf
                                        <button type="submit" class="text-xs text-red-500 hover:underline whitespace-nowrap">
                                            Reject
                                        </button>
                                    </form>
                                @endif

                            </div>
                        </td>

                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-10 text-center text-gray-400 text-sm">
                            No deposit slip submissions yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($submissions->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $submissions->links() }}
            </div>
        @endif

        {{-- Edit Modal --}}
        <div x-show="editOpen" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
             @keydown.escape.window="editOpen = false">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4" @click.outside="editOpen = false">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-900">Edit Deposit Slip</h2>
                    <button @click="editOpen = false" class="text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form :action="'/deposit-slips/' + editId" method="POST" class="px-6 py-4 space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Bank Name</label>
                            <input type="text" name="bank_name" x-model="form.bank_name"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Account Number</label>
                            <input type="text" name="account_number" x-model="form.account_number"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Amount (₱)</label>
                            <input type="number" step="0.01" name="amount" x-model="form.amount"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Deposit Date</label>
                            <input type="date" name="deposit_date" x-model="form.deposit_date"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Reference Number</label>
                        <input type="text" name="reference_number" x-model="form.reference_number"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Passbook</label>
                        <select name="passbook_id" x-model="form.passbook_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">— No passbook matched —</option>
                            @foreach($passbooks as $pb)
                                <option value="{{ $pb->id }}">
                                    {{ $pb->branch?->name }} — {{ $pb->bank_name }} {{ $pb->account_number }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Changing this will update the passbook deposit entry.</p>
                        <p x-show="!form.passbook_id" class="text-xs text-amber-600 mt-1">A passbook must be selected to approve this submission.</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                        <button type="button" @click="editOpen = false"
                                class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit"
                                :disabled="!form.passbook_id"
                                :class="form.passbook_id
                                    ? 'bg-indigo-600 hover:bg-indigo-700 text-white cursor-pointer'
                                    : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                                class="px-4 py-2 text-sm font-medium rounded-lg">
                            Save &amp; Approve
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    {{-- Registered Staff --}}
    <div class="mt-8">
        <h2 class="text-base font-semibold text-gray-800 mb-3">Registered Bot Staff</h2>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="overflow-x-auto">
                @php $staffList = \App\Models\MessengerStaff::with('branch')->latest()->get(); @endphp
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Facebook Name</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Employee Code</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Branch</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Registered</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Submissions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($staffList as $s)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $s->fb_name ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-gray-700">{{ $s->employee_code ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $s->branch?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    {{ $s->isActive() ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                    {{ $s->isActive() ? 'Active' : 'Pending Code' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ $s->registered_at?->format('M d, Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $s->submissions()->count() }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-400 text-sm">No staff registered yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
function depositSlips() {
    return {
        editOpen: false,
        editId: null,
        form: {
            bank_name: '',
            account_number: '',
            amount: '',
            deposit_date: '',
            reference_number: '',
            passbook_id: '',
        },
        openEdit(id, data) {
            this.editId = id;
            this.form = {
                bank_name:        data.bank_name        ?? '',
                account_number:   data.account_number   ?? '',
                amount:           data.amount           ?? '',
                deposit_date:     data.deposit_date     ?? '',
                reference_number: data.reference_number ?? '',
                passbook_id:      data.passbook_id      ?? '',
            };
            this.editOpen = true;
        },
    }
}
</script>
@endpush
@endsection
