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

        {{-- Filter --}}
        <form method="GET" class="flex items-center gap-2">
            <select name="status" onchange="this.form.submit()"
                    class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="" @selected(!request('status'))>All</option>
                <option value="success" @selected(request('status') === 'success')>Parsed</option>
                <option value="low_confidence" @selected(request('status') === 'low_confidence')>Low Confidence</option>
                <option value="failed" @selected(request('status') === 'failed')>Parse Failed</option>
                <option value="duplicate" @selected(request('status') === 'duplicate')>Duplicate</option>
                <option value="unreviewed" @selected(request('status') === 'unreviewed')>Unreviewed</option>
            </select>
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Staff</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Branch</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Bank / Account</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Deposit Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Reference</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Passbook</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Submitted</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($submissions as $sub)
                    <tr class="hover:bg-gray-50 {{ $sub->isReviewed() ? 'opacity-60' : '' }}">

                        {{-- Staff --}}
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $sub->staff?->fb_name ?? '—' }}</div>
                            <div class="text-xs text-gray-500">{{ $sub->staff?->employee_code ?? $sub->fb_sender_id }}</div>
                        </td>

                        {{-- Branch --}}
                        <td class="px-4 py-3 text-gray-700">
                            {{ $sub->branch?->name ?? '—' }}
                        </td>

                        {{-- Bank / Account --}}
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $sub->bank_name ?? '—' }}</div>
                            <div class="text-xs text-gray-500">{{ $sub->account_number ?? '' }}</div>
                        </td>

                        {{-- Amount --}}
                        <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900">
                            @if($sub->amount !== null)
                                ₱{{ number_format($sub->amount, 2) }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>

                        {{-- Deposit Date --}}
                        <td class="px-4 py-3 text-gray-700">
                            {{ $sub->deposit_date?->format('M d, Y') ?? '—' }}
                        </td>

                        {{-- Reference --}}
                        <td class="px-4 py-3 font-mono text-xs text-gray-700">
                            {{ $sub->reference_number ?? '—' }}
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3">
                            <div class="flex flex-col gap-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    {{ $sub->is_duplicate ? 'bg-orange-100 text-orange-800' : $sub->statusBadgeClass() }}">
                                    {{ $sub->statusLabel() }}
                                </span>
                                @if($sub->confidence_notes)
                                    <span class="text-xs text-gray-500 italic">{{ $sub->confidence_notes }}</span>
                                @endif
                            </div>
                        </td>

                        {{-- Passbook --}}
                        <td class="px-4 py-3">
                            @if($sub->passbook)
                                <a href="{{ route('passbooks.show', $sub->passbook_id) }}"
                                   class="text-indigo-600 hover:underline text-xs">
                                    {{ $sub->passbook->label() }}
                                </a>
                            @else
                                <span class="text-xs text-gray-400">No match</span>
                            @endif
                        </td>

                        {{-- Submitted --}}
                        <td class="px-4 py-3 text-xs text-gray-500">
                            {{ $sub->created_at->format('M d, Y H:i') }}
                            @if($sub->isReviewed())
                                <div class="text-green-600 mt-0.5">Reviewed</div>
                            @endif
                        </td>

                        {{-- Actions --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                {{-- View image --}}
                                @if($sub->image_path)
                                    <a href="{{ route('deposit-slips.image', $sub) }}" target="_blank"
                                       class="text-xs text-indigo-600 hover:underline">
                                        View Slip
                                    </a>
                                @endif

                                {{-- Mark reviewed --}}
                                @if(!$sub->isReviewed())
                                    <form method="POST" action="{{ route('deposit-slips.review', $sub) }}">
                                        @csrf
                                        <button type="submit"
                                                class="text-xs text-gray-500 hover:text-green-700 hover:underline">
                                            Mark Reviewed
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

        {{-- Pagination --}}
        @if($submissions->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $submissions->links() }}
            </div>
        @endif
    </div>

    {{-- Registered Staff table --}}
    <div class="mt-8">
        <h2 class="text-base font-semibold text-gray-800 mb-3">Registered Bot Staff</h2>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="overflow-x-auto">
                @php
                    $staffList = \App\Models\MessengerStaff::with('branch')->latest()->get();
                @endphp
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
                            <td class="px-4 py-3 text-xs text-gray-500">
                                {{ $s->registered_at?->format('M d, Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $s->submissions()->count() }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-400 text-sm">
                                No staff registered yet.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
