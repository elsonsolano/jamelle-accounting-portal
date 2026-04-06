@extends('layouts.app')
@section('title', 'PayMaya Sync')

@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-gray-800">PayMaya Settlement Sync</h1>
    <div class="flex items-center gap-3">
        @if($hasRefreshToken)
            <form method="POST" action="{{ route('paymaya.sync-now') }}">
                @csrf
                <button type="submit"
                        class="text-sm bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                    Sync Now
                </button>
            </form>
        @endif
        <a href="{{ route('paymaya.auth') }}"
           class="text-sm bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
            {{ $hasRefreshToken ? 'Reconnect Gmail' : 'Connect Gmail' }}
        </a>
    </div>
</div>

{{-- Gmail connection status --}}
<div class="mb-6 p-4 rounded border {{ $hasRefreshToken ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200' }}">
    <div class="flex items-center gap-2 text-sm">
        <span class="{{ $hasRefreshToken ? 'text-green-700' : 'text-yellow-700' }} font-medium">
            {{ $hasRefreshToken ? '● Gmail Connected — System Crawl runs every Mon-Fri, 11PM automatically.' : '● Gmail Not Connected — click "Connect Gmail" to authorize' }}
        </span>
    </div>
</div>

{{-- Import history --}}
<div class="bg-white rounded shadow border border-gray-100 overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
                <th class="px-4 py-3 text-left">Credit Date</th>
                <th class="px-4 py-3 text-left">Subject</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-left">Lines</th>
                <th class="px-4 py-3 text-left">Processed At</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($imports as $import)
                <tr class="hover:bg-gray-50" x-data="{ open: false }">
                    <td class="px-4 py-3 font-medium text-gray-800">
                        {{ $import->credit_date->format('M d, Y') }}
                    </td>
                    <td class="px-4 py-3 text-gray-600 text-xs">{{ $import->subject }}</td>
                    <td class="px-4 py-3">
                        @php
                            $badges = [
                                'processed' => 'bg-green-100 text-green-700',
                                'duplicate' => 'bg-yellow-100 text-yellow-700',
                                'failed'    => 'bg-red-100 text-red-700',
                            ];
                        @endphp
                        <span class="inline-block px-2 py-0.5 rounded text-xs font-medium {{ $badges[$import->status] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ ucfirst($import->status) }}
                        </span>
                        @if($import->notes)
                            <span class="text-xs text-gray-400 ml-1">— {{ $import->notes }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if($import->lines->isNotEmpty())
                            <button @click="open = !open" class="text-xs text-indigo-600 hover:underline">
                                {{ $import->lines->count() }} line(s) ▾
                            </button>
                        @else
                            <span class="text-xs text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-400 text-xs">
                        {{ $import->processed_at?->format('M d, Y H:i') ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <form method="POST" action="{{ route('paymaya.destroy', $import) }}"
                              onsubmit="return confirm('Delete this import and its passbook entries?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:underline">Delete</button>
                        </form>
                    </td>
                </tr>
                {{-- Expanded lines --}}
                <tr x-show="open" x-cloak class="bg-indigo-50">
                    <td colspan="6" class="px-6 py-3">
                        <table class="w-full text-xs">
                            <thead class="text-gray-500">
                                <tr>
                                    <th class="text-left py-1">Bank Account</th>
                                    <th class="text-left py-1">Branch / Passbook</th>
                                    <th class="text-right py-1">Amount</th>
                                    <th class="text-left py-1">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($import->lines as $line)
                                    <tr>
                                        <td class="py-1 font-mono">{{ $line->bank_account }}</td>
                                        <td class="py-1">
                                            @if($line->passbook)
                                                {{ $line->passbook->branch->name }} — {{ $line->passbook->bank_name }}
                                            @else
                                                <span class="text-red-500">Unmatched</span>
                                            @endif
                                        </td>
                                        <td class="py-1 text-right font-medium">₱{{ number_format($line->amount, 2) }}</td>
                                        <td class="py-1">
                                            @php
                                                $lineBadges = [
                                                    'posted'     => 'bg-green-100 text-green-700',
                                                    'duplicate'  => 'bg-yellow-100 text-yellow-700',
                                                    'unmatched'  => 'bg-red-100 text-red-700',
                                                ];
                                            @endphp
                                            <span class="inline-block px-2 py-0.5 rounded font-medium {{ $lineBadges[$line->status] ?? '' }}">
                                                {{ ucfirst($line->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-gray-400">
                        No imports yet. {{ $hasRefreshToken ? 'Click "Sync Now" to run manually.' : 'Connect Gmail first.' }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($imports->hasPages())
    <div class="mt-4">{{ $imports->links() }}</div>
@endif

@endsection
