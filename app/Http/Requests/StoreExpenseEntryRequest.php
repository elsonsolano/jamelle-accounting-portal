<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['Admin', 'Accountant']);
    }

    public function rules(): array
    {
        return [
            'period_id'   => ['required', 'exists:expense_periods,id'],
            'date'        => ['required', 'date'],
            'category_id' => ['required', 'exists:expense_categories,id'],
            'particular'  => ['required', 'string', 'max:255'],
            'amount'      => ['required', 'numeric', 'min:0'],
            'notes'       => ['nullable', 'string'],
            'sort_order'  => ['nullable', 'integer'],
        ];
    }
}
