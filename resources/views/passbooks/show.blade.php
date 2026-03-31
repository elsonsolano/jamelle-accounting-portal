@extends('layouts.app')
@section('title', $passbook->bank_name . ' Ledger')

@section('content')

<div class="flex flex-wrap items-start justify-between gap-3 mb-4">
    <div>
        <a href="{{ route('passbooks.index') }}" class="text-sm text-gray-500 hover:underline">&larr; All Passbooks</a>
        <h1 class="text-xl font-bold text-gray-800 mt-1">
            {{ $passbook->bank_name }}
            @if($passbook->account_number)
                <span class="text-gray-400 font-normal text-base">— {{ $passbook->account_number }}</span>
            @endif
        </h1>
        <p class="text-sm text-gray-500">{{ $passbook->branch->name }}</p>
    </div>
    <div class="flex items-center gap-3">
        <div class="text-right">
            <div class="text-xs text-gray-400">Current Balance</div>
            <div class="text-lg font-bold text-indigo-700">₱{{ $passbook->currentBalance() }}</div>
        </div>
        <a href="{{ route('passbook-entries.create', $passbook) }}"
           class="text-sm bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 whitespace-nowrap">
            + Add Transaction
        </a>
    </div>
</div>

<div class="bg-white rounded shadow border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
                <th class="px-4 py-3 text-left">Date</th>
                <th class="px-4 py-3 text-left">Particulars</th>
                <th class="px-4 py-3 text-left">Type</th>
                <th class="px-4 py-3 text-right">Withdrawal</th>
                <th class="px-4 py-3 text-right">Deposit</th>
                <th class="px-4 py-3 text-right">Balance</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">

            {{-- Opening Balance Row --}}
            <tr class="bg-gray-50">
                <td class="px-4 py-2 text-gray-400 text-xs">{{ $passbook->opening_date->format('M d, Y') }}</td>
                <td class="px-4 py-2 text-gray-500 text-xs italic" colspan="4">Opening Balance</td>
                <td class="px-4 py-2 text-right font-semibold text-gray-700">₱{{ number_format($passbook->opening_balance, 2) }}</td>
                <td></td>
            </tr>

            @forelse($rows as $row)
                @php $entry = $row['entry']; @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ $entry->date->format('M d, Y') }}</td>
                    <td class="px-4 py-3 text-gray-800">
                        <div class="flex items-center gap-2">
                            {{ $entry->particulars }}
                            @if($entry->source === 'paymaya_auto')
                                <span class="inline-block px-1.5 py-0.5 rounded text-xs font-medium bg-violet-100 text-violet-700">Auto Sync</span>
                            @endif
                        </div>
                        @if($entry->linkedEntry)
                            <span class="text-xs text-gray-400 block">
                                {{ in_array($entry->type, ['transfer_out']) ? '→' : '←' }}
                                {{ $entry->linkedEntry->passbook->branch->name }} — {{ $entry->linkedEntry->passbook->bank_name }}
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @php
                            $typeLabels = [
                                'deposit'      => ['label' => 'Deposit',      'class' => 'bg-green-100 text-green-700'],
                                'withdrawal'   => ['label' => 'Withdrawal',   'class' => 'bg-red-100 text-red-700'],
                                'transfer_in'  => ['label' => 'Transfer In',  'class' => 'bg-blue-100 text-blue-700'],
                                'transfer_out' => ['label' => 'Transfer Out', 'class' => 'bg-orange-100 text-orange-700'],
                                'bank_charge'  => ['label' => 'Bank Charge',  'class' => 'bg-yellow-100 text-yellow-700'],
                                'interest'     => ['label' => 'Interest',     'class' => 'bg-teal-100 text-teal-700'],
                            ];
                            $t = $typeLabels[$entry->type] ?? ['label' => $entry->type, 'class' => 'bg-gray-100 text-gray-600'];
                        @endphp
                        <span class="inline-block px-2 py-0.5 rounded text-xs font-medium {{ $t['class'] }}">
                            {{ $t['label'] }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right text-red-600">
                        @if($entry->isDebit()) ₱{{ number_format($entry->amount, 2) }} @else — @endif
                    </td>
                    <td class="px-4 py-3 text-right text-green-600">
                        @if($entry->isCredit()) ₱{{ number_format($entry->amount, 2) }} @else — @endif
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-700">
                        ₱{{ number_format($row['balance'], 2) }}
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('passbook-entries.edit', $entry) }}"
                           class="text-xs text-indigo-600 hover:underline mr-2">Edit</a>
                        <form method="POST" action="{{ route('passbook-entries.destroy', $entry) }}" class="inline"
                              onsubmit="return confirm('Delete this transaction?{{ $entry->linked_entry_id ? ' This will also delete the linked transfer entry.' : '' }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:underline">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">
                        No transactions yet. <a href="{{ route('passbook-entries.create', $passbook) }}" class="text-indigo-500 hover:underline">Add one.</a>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    </div>{{-- /overflow-x-auto --}}
</div>

@endsection
