<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertGrossSalesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['Admin', 'Accountant']);
    }

    public function rules(): array
    {
        return [
            'period_id' => ['required', 'exists:expense_periods,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'amount'    => ['required', 'numeric', 'min:0'],
        ];
    }
}
