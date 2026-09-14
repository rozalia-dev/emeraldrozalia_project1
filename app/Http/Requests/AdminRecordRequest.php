<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminRecordRequest extends FormRequest
{
    private const STATUSES = [
        'active', 'draft', 'planned', 'in-progress', 'completed',
        'on-hold', 'new', 'pending', 'approved', 'rejected',
        'open', 'closed', 'archived',
    ];

    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    protected function prepareForValidation(): void
    {
        foreach (['title', 'reference', 'notes'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => trim((string) $this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'reference' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'string', Rule::in(self::STATUSES)],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'record_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
