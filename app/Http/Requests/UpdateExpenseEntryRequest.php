<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['Admin', 'Accountant']);
    }

    public function rules(): array
    {
        return [
            'date'        => ['sometimes', 'required', 'date'],
            'category_id' => ['sometimes', 'required', 'exists:expense_categories,id'],
            'particular'  => ['sometimes', 'required', 'string', 'max:255'],
            'amount'      => ['sometimes', 'required', 'numeric', 'min:0'],
            'notes'       => ['nullable', 'string'],
            'sort_order'  => ['nullable', 'integer'],
        ];
    }
}
