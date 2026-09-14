<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesCommunication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommunicationWorkItemRequest extends FormRequest
{
    use AuthorizesCommunication;
    public function authorize(): bool
    {
        return $this->communicationAuthorized($this->isMethod('POST') ? 'create' : 'edit');
    }

    protected function prepareForValidation(): void
    {
        foreach (['title', 'reference', 'description', 'category', 'type', 'assigned_to_name', 'entity', 'source', 'notes'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $this->merge([$field => trim((string) $this->input($field))]);
            }
        }

        $headerKey = trim((string) $this->header('Idempotency-Key', ''));
        $inputKey = trim((string) $this->input('idempotency_key', ''));
        $this->merge([
            'idempotency_key' => $headerKey !== '' ? $headerKey : ($inputKey !== '' ? $inputKey : null),
        ]);
    }

    public function rules(): array
    {
        $section = (string) ($this->route('section') ?: $this->input('_section'));
        $isAlert = $section === 'alerts-notifications';

        return [
            'title' => ['required', 'string', 'max:180'],
            'reference' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'string', Rule::in($isAlert
                ? ['unread', 'in-progress', 'acknowledged', 'escalated', 'resolved']
                : ['pending', 'in-progress', 'completed', 'overdue', 'cancelled'])],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'record_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'category' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'max:120'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'medium', 'high', 'urgent'])],
            'assigned_to_name' => ['nullable', 'string', 'max:180'],
            'entity' => ['nullable', 'string', 'max:180'],
            'conversation_id' => ['nullable', 'integer', 'min:1', 'exists:conversations,id'],
            'conversation_uuid' => ['nullable', 'uuid'],
            'order_id' => ['nullable', 'integer', 'min:1', 'exists:orders,id'],
            'order_uuid' => ['nullable', 'uuid'],
            'source' => ['nullable', 'string', 'max:120'],
            'due_at' => ['nullable', 'date'],
            'severity' => ['nullable', Rule::in(['critical', 'high', 'medium', 'low', 'informational'])],
            'idempotency_key' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'expected_version' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
