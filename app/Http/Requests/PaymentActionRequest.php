<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin
            || $this->user()?->hasAnyPermission(['online.sales.edit', 'reports.edit']);
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['capture', 'refund', 'cancel'])],
            'expected_version' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'refund_amount' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/'],
            'transition_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
