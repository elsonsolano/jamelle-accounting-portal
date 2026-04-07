@extends('layouts.app')
@section('title', 'Passbooks')

@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-gray-800">Passbooks</h1>
    @can('manage users')
        <a href="{{ route('passbooks.create') }}"
           class="text-sm bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
            + New Passbook
        </a>
    @endcan
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
    @forelse($branches as $branch)
        @foreach($branch->passbooks as $passbook)
            <a href="{{ route('passbooks.show', $passbook) }}"
               class="bg-white border border-gray-100 rounded shadow-sm p-4 hover:border-indigo-300 hover:shadow transition block">
                <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">{{ $branch->name }}</div>
                <div class="text-base font-semibold text-gray-800">{{ $passbook->bank_name }}</div>
                @if($passbook->account_name)
                    <div class="text-xs text-gray-500 mt-0.5">{{ $passbook->account_name }}</div>
                @endif
                @if($passbook->account_number)
                    <div class="text-xs text-gray-400 mt-0.5">{{ $passbook->account_number }}</div>
                @endif
                <div class="mt-3 text-sm font-medium text-indigo-700">
                    Balance: ₱{{ $passbook->currentBalance() }}
                </div>
            </a>
        @endforeach
    @empty
        <div class="col-span-full text-center py-16 text-gray-400 text-sm">No passbooks yet.</div>
    @endforelse
</div>

@endsection
