<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FranchiseApplicationActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return (bool) ($user && ($user->is_admin || $user->hasAnyPermission([
            'applications.leads.edit',
            'applications.leads.update',
            'franchise.update',
            'franchise.management.edit',
        ])));
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
        $isConversion = (string) $this->route('action') === 'convert';

        return [
            'idempotency_key' => $isConversion
                ? ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/']
                : ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ];
    }
}
