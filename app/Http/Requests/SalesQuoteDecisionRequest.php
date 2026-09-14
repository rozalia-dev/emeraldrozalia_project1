<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesQuoteDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('note') && is_string($this->input('note'))) {
            $this->merge(['note' => trim((string) $this->input('note'))]);
        }
    }

    public function rules(): array
    {
        return [
            'expected_version' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
