<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpensePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['Admin', 'Accountant']);
    }

    public function rules(): array
    {
        return [
            'vat_itr_estimate' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
