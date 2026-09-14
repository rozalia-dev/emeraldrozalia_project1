<?php

namespace App\Http\Requests;

use App\Services\FranchiseStoreLifecycleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FranchiseStoreActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user && ($user->is_admin || $user->hasAnyPermission([
            'franchise.retail.stores.edit',
            'franchise.retail.stores.update',
            'franchise.update',
            'franchise.management.edit',
        ])));
    }

    protected function prepareForValidation(): void
    {
        $headerKey = trim((string) $this->header('Idempotency-Key', ''));
        $inputKey = trim((string) $this->input('idempotency_key', ''));

        $this->merge([
            'action' => (string) $this->route('action'),
            'idempotency_key' => $headerKey !== '' ? $headerKey : ($inputKey !== '' ? $inputKey : null),
        ]);
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(FranchiseStoreLifecycleService::ACTIONS)],
            'idempotency_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'expected_version' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'reason' => ['required_if:action,suspend,terminate', 'nullable', 'string', 'max:1000'],
        ];
    }
}
