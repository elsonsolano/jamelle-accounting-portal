@extends('layouts.app')
@section('title', 'Edit Transaction')

@section('content')

<div class="flex items-center justify-between mb-4">
    <div>
        <a href="{{ route('passbooks.show', $passbook) }}" class="text-sm text-gray-500 hover:underline">
            &larr; {{ $passbook->bank_name }}{{ $passbook->account_number ? ' — ' . $passbook->account_number : '' }}
        </a>
        <h1 class="text-xl font-bold text-gray-800 mt-1">Edit Transaction</h1>
    </div>
</div>

<div class="max-w-lg bg-white rounded shadow border border-gray-100 p-6">

    @if($passbookEntry->linked_entry_id)
        <div class="mb-4 bg-blue-50 border border-blue-200 text-blue-800 rounded px-4 py-3 text-sm">
            This is a transfer entry. Editing the date, particulars, or amount will also update the linked counter-entry.
        </div>
    @endif

    <form method="POST" action="{{ route('passbook-entries.update', $passbookEntry) }}" class="space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
            <input type="date" name="date" value="{{ old('date', $passbookEntry->date->format('Y-m-d')) }}" required
                   class="w-full text-sm border-gray-300 rounded focus:ring-indigo-400
                          @error('date') border-red-400 @enderror">
            @error('date')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
            <input type="text" value="{{ ucwords(str_replace('_', ' ', $passbookEntry->type)) }}" disabled
                   class="w-full text-sm border-gray-200 bg-gray-50 rounded text-gray-500">
            <p class="text-xs text-gray-400 mt-1">Type cannot be changed after creation.</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Particulars</label>
            <input type="text" name="particulars" value="{{ old('particulars', $passbookEntry->particulars) }}" required
                   class="w-full text-sm border-gray-300 rounded focus:ring-indigo-400
                          @error('particulars') border-red-400 @enderror">
            @error('particulars')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
            <input type="number" name="amount" value="{{ old('amount', $passbookEntry->amount) }}"
                   step="0.01" min="0.01" required
                   class="w-full text-sm border-gray-300 rounded focus:ring-indigo-400
                          @error('amount') border-red-400 @enderror">
            @error('amount')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="pt-2 flex gap-3">
            <button type="submit"
                    class="bg-indigo-600 text-white text-sm font-medium px-5 py-2 rounded hover:bg-indigo-700">
                Save Changes
            </button>
            <a href="{{ route('passbooks.show', $passbook) }}"
               class="text-sm text-gray-500 px-4 py-2 rounded border border-gray-300 hover:bg-gray-50">
                Cancel
            </a>
        </div>
    </form>
</div>

@endsection
