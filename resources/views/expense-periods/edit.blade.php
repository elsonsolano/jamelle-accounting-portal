@extends('layouts.app')
@section('title', 'Edit Period')

@section('content')
<div class="max-w-lg">
    <h1 class="text-xl font-bold text-gray-800 mb-6">
        Edit — {{ $expensePeriod->branch->name }}
        {{ \Carbon\Carbon::create($expensePeriod->year, $expensePeriod->month)->format('F Y') }}
    </h1>

    <form method="POST" action="{{ route('expense-periods.update', $expensePeriod) }}"
          class="bg-white rounded shadow border border-gray-100 p-6 space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">VAT/ITR Estimate</label>
            <input type="number" name="vat_itr_estimate"
                   value="{{ old('vat_itr_estimate', $expensePeriod->vat_itr_estimate) }}"
                   step="0.01" min="0"
                   class="w-full border-gray-300 rounded text-sm focus:ring-indigo-500">
            @error('vat_itr_estimate')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit"
                    class="bg-indigo-600 text-white text-sm px-5 py-2 rounded hover:bg-indigo-700">
                Save Changes
            </button>
            <a href="{{ route('expense-periods.show', $expensePeriod) }}"
               class="text-sm text-gray-500 px-5 py-2 rounded hover:bg-gray-100">Cancel</a>
        </div>
    </form>
</div>
@endsection
