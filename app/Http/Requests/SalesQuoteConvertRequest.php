<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesQuoteConvertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        $headerKey = trim((string) $this->header('Idempotency-Key', ''));
        $inputKey = trim((string) $this->input('idempotency_key', ''));
        $this->merge([
            'idempotency_key' => $headerKey !== '' ? $headerKey : ($inputKey !== '' ? $inputKey : null),
        ]);
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'expected_version' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
