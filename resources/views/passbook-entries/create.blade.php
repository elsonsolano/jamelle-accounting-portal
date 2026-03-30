@extends('layouts.app')
@section('title', 'Add Transaction')

@section('content')

<div class="flex items-center justify-between mb-4">
    <div>
        <a href="{{ route('passbooks.show', $passbook) }}" class="text-sm text-gray-500 hover:underline">
            &larr; {{ $passbook->bank_name }}{{ $passbook->account_number ? ' — ' . $passbook->account_number : '' }}
        </a>
        <h1 class="text-xl font-bold text-gray-800 mt-1">Add Transaction</h1>
    </div>
</div>

<div class="max-w-lg bg-white rounded shadow border border-gray-100 p-6">
    <form method="POST" action="{{ route('passbook-entries.store', $passbook) }}" class="space-y-4"
          x-data="{ type: '{{ old('type', 'deposit') }}' }">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
            <input type="date" name="date" value="{{ old('date', date('Y-m-d')) }}" required
                   class="w-full text-sm border-gray-300 rounded focus:ring-indigo-400
                          @error('date') border-red-400 @enderror">
            @error('date')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
            <select name="type" x-model="type" required
                    class="w-full text-sm border-gray-300 rounded focus:ring-indigo-400
                           @error('type') border-red-400 @enderror">
                <option value="deposit">Deposit</option>
                <option value="withdrawal">Withdrawal</option>
                <option value="transfer_out">Transfer Out</option>
                <option value="transfer_in">Transfer In</option>
                <option value="bank_charge">Bank Charge</option>
                <option value="interest">Interest</option>
            </select>
            @error('type')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Transfer target passbook --}}
        <div x-show="type === 'transfer_out' || type === 'transfer_in'">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Transfer
                <span x-text="type === 'transfer_out' ? 'To' : 'From'"></span>
            </label>
            <select name="transfer_passbook_id"
                    class="w-full text-sm border-gray-300 rounded focus:ring-indigo-400
                           @error('transfer_passbook_id') border-red-400 @enderror">
                <option value="">Select passbook…</option>
                @foreach($otherPassbooks as $other)
                    <option value="{{ $other->id }}" {{ old('transfer_passbook_id') == $other->id ? 'selected' : '' }}>
                        {{ $other->branch->name }} — {{ $other->bank_name }}
                        @if($other->account_number) ({{ $other->account_number }}) @endif
                    </option>
                @endforeach
            </select>
            @error('transfer_passbook_id')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Particulars</label>
            <input type="text" name="particulars" value="{{ old('particulars') }}" required
                   class="w-full text-sm border-gray-300 rounded focus:ring-indigo-400
                          @error('particulars') border-red-400 @enderror"
                   placeholder="e.g. PayMaya Remittance, Rental Payment">
            @error('particulars')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
            <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01" required
                   class="w-full text-sm border-gray-300 rounded focus:ring-indigo-400
                          @error('amount') border-red-400 @enderror">
            @error('amount')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="pt-2 flex gap-3">
            <button type="submit"
                    class="bg-indigo-600 text-white text-sm font-medium px-5 py-2 rounded hover:bg-indigo-700">
                Save Transaction
            </button>
            <a href="{{ route('passbooks.show', $passbook) }}"
               class="text-sm text-gray-500 px-4 py-2 rounded border border-gray-300 hover:bg-gray-50">
                Cancel
            </a>
        </div>
    </form>
</div>

@endsection
