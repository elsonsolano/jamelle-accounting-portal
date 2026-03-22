@extends('layouts.app')
@section('title', 'New Expense Period')

@section('content')
<div class="max-w-lg">
    <h1 class="text-xl font-bold text-gray-800 mb-6">New Expense Period</h1>

    <form method="POST" action="{{ route('expense-periods.store') }}"
          class="bg-white rounded shadow border border-gray-100 p-6 space-y-5">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
            <select name="branch_id" required
                    class="w-full border-gray-300 rounded text-sm focus:ring-indigo-500 @error('branch_id') border-red-400 @enderror">
                <option value="">Select branch…</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>
                        {{ $branch->name }}
                    </option>
                @endforeach
            </select>
            @error('branch_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                <select name="month" required
                        class="w-full border-gray-300 rounded text-sm focus:ring-indigo-500 @error('month') border-red-400 @enderror">
                    <option value="">Month…</option>
                    @foreach(range(1,12) as $m)
                        <option value="{{ $m }}" @selected(old('month') == $m)>
                            {{ \Carbon\Carbon::create(null, $m)->format('F') }}
                        </option>
                    @endforeach
                </select>
                @error('month')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                <input type="number" name="year" value="{{ old('year', now()->year) }}"
                       min="2000" max="2100" required
                       class="w-full border-gray-300 rounded text-sm focus:ring-indigo-500 @error('year') border-red-400 @enderror">
                @error('year')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">VAT/ITR Estimate</label>
            <input type="number" name="vat_itr_estimate" value="{{ old('vat_itr_estimate', 0) }}"
                   step="0.01" min="0"
                   class="w-full border-gray-300 rounded text-sm focus:ring-indigo-500">
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit"
                    class="bg-indigo-600 text-white text-sm px-5 py-2 rounded hover:bg-indigo-700">
                Create Period
            </button>
            <a href="{{ route('expense-periods.index') }}"
               class="text-sm text-gray-500 px-5 py-2 rounded hover:bg-gray-100">Cancel</a>
        </div>
    </form>
</div>
@endsection
