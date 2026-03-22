<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpensePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['Admin', 'Accountant']);
    }

    public function rules(): array
    {
        return [
            'branch_id'        => ['required', 'exists:branches,id'],
            'month'            => ['required', 'integer', 'min:1', 'max:12'],
            'year'             => ['required', 'integer', 'min:2000', 'max:2100'],
            'vat_itr_estimate' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
