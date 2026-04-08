@extends('layouts.app')

@section('title', 'Messenger Bot Utilities')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">

    <h1 class="text-2xl font-bold text-gray-900">Messenger Bot Utilities</h1>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Send Reminder --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <div>
            <h2 class="text-base font-semibold text-gray-900">Send Deposit Slip Reminder</h2>
            <p class="text-sm text-gray-500 mt-1">
                Sends the daily reminder message to all {{ $staff->count() }} registered staff on Messenger.
                This runs automatically every day at 10:00 AM PHT.
            </p>
        </div>

        <form method="POST" action="{{ route('messenger.send-reminder-now') }}">
            @csrf
            <button type="submit"
                onclick="return confirm('Send reminder to all {{ $staff->count() }} staff now?')"
                class="bg-indigo-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-indigo-700">
                Send Reminder Now
            </button>
        </form>
    </div>

    {{-- Registered Staff --}}
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-base font-semibold text-gray-900">Registered Staff ({{ $staff->count() }})</h2>
            <p class="text-sm text-gray-500 mt-0.5">These are the users who will receive the reminder.</p>
        </div>
        @if($staff->isEmpty())
            <p class="px-6 py-8 text-center text-sm text-gray-400">No staff registered yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                        <tr>
                            <th class="text-left px-6 py-3">Name</th>
                            <th class="text-left px-6 py-3">Employee Code</th>
                            <th class="text-left px-6 py-3">Branch</th>
                            <th class="text-left px-6 py-3">Status</th>
                            <th class="text-left px-6 py-3">Registered</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($staff as $member)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-3 font-medium text-gray-900">{{ $member->fb_name }}</td>
                                <td class="px-6 py-3 font-mono text-gray-600">{{ $member->employee_code ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $member->branch?->name ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    @if($member->state === 'active')
                                        <span class="inline-block px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Active</span>
                                    @else
                                        <span class="inline-block px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">{{ ucfirst($member->state) }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-gray-500">{{ $member->registered_at?->format('M d, Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>
@endsection
