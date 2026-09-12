<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommunicationEmailIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'customer' => ['sometimes', 'nullable', 'string', 'max:120'],
            'order' => ['sometimes', 'nullable', 'string', 'max:120'],
            'uid' => ['sometimes', 'nullable', 'string', 'max:120'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(['new', 'open', 'pending', 'closed'])],
            'priority' => ['sometimes', 'nullable', 'string', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->filled('date_from') || ! $this->filled('date_to')) {
                return;
            }

            if (strtotime((string) $this->input('date_from')) > strtotime((string) $this->input('date_to'))) {
                $validator->errors()->add('date_to', 'The end date must be on or after the start date.');
            }
        });
    }
}
