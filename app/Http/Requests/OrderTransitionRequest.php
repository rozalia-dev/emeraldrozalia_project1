<?php

namespace App\Http\Requests;

use App\Services\OrderLifecycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(OrderLifecycle::ORDER_STATUSES)],
            'payment_status' => ['required', 'string', Rule::in(OrderLifecycle::PAYMENT_STATUSES)],
            'fulfillment_status' => ['sometimes', 'nullable', 'string', Rule::in(OrderLifecycle::FULFILLMENT_STATUSES)],
            'expected_version' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'transition_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
