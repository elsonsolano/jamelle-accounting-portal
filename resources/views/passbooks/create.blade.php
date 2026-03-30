@extends('layouts.app')
@section('title', 'New Passbook')

@section('content')

<div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-bold text-gray-800">New Passbook</h1>
    <a href="{{ route('passbooks.index') }}" class="text-sm text-gray-500 hover:underline">&larr; All Passbooks</a>
</div>

<div class="max-w-lg bg-white rounded shadow border border-gray-100 p-6">
    <form method="POST" action="{{ route('passbooks.store') }}" class="space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
            <select name="branch_id" required
                    class="w-full text-sm border-gray-300 rounded focus:ring-indigo-400
                           @error('branch_id') border-red-400 @enderror">
                <option value="">Select a branch…</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>
                        {{ $branch->name }}
                    </option>
                @endforeach
            </select>
            @error('branch_id')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
            <input type="text" name="bank_name" value="{{ old('bank_name') }}" required placeholder="e.g. BDO, BPI"
                   class="w-full text-sm border-gray-300 rounded focus:ring-indigo-400
                          @error('bank_name') border-red-400 @enderror">
            @error('bank_name')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Account Name <span class="text-gray-400 font-normal">(optional)</span></label>
            <input type="text" name="account_name" value="{{ old('account_name') }}"
                   class="w-full text-sm border-gray-300 rounded focus:ring-indigo-400">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Account Number <span class="text-gray-400 font-normal">(optional)</span></label>
            <input type="text" name="account_number" value="{{ old('account_number') }}"
                   class="w-full text-sm border-gray-300 rounded focus:ring-indigo-400">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Opening Balance</label>
            <input type="number" name="opening_balance" value="{{ old('opening_balance', '0') }}"
                   step="0.01" min="0" required
                   class="w-full text-sm border-gray-300 rounded focus:ring-indigo-400
                          @error('opening_balance') border-red-400 @enderror">
            @error('opening_balance')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Opening Date</label>
            <input type="date" name="opening_date" value="{{ old('opening_date') }}" required
                   class="w-full text-sm border-gray-300 rounded focus:ring-indigo-400
                          @error('opening_date') border-red-400 @enderror">
            @error('opening_date')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="pt-2 flex gap-3">
            <button type="submit"
                    class="bg-indigo-600 text-white text-sm font-medium px-5 py-2 rounded hover:bg-indigo-700">
                Create Passbook
            </button>
            <a href="{{ route('passbooks.index') }}"
               class="text-sm text-gray-500 px-4 py-2 rounded border border-gray-300 hover:bg-gray-50">
                Cancel
            </a>
        </div>
    </form>
</div>

@endsection
