<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReturnRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => strtolower(trim((string) $this->input('type', ''))),
            'reason' => trim((string) $this->input('reason', '')),
            'details' => filled($this->input('details')) ? trim((string) $this->input('details')) : null,
            'idempotency_key' => $this->idempotencyKey(),
        ]);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['return', 'exchange'])],
            'reason' => ['required', 'string', 'max:150'],
            'details' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ];
    }

    public function idempotencyKey(): string
    {
        $header = $this->header('Idempotency-Key');
        $candidate = is_string($header) && trim($header) !== ''
            ? trim($header)
            : trim((string) $this->input('idempotency_key', ''));

        return $candidate !== '' ? $candidate : (string) Str::uuid();
    }
}
