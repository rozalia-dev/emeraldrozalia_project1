<?php

namespace App\Http\Requests;

use App\Models\Approval;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApprovalRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        if (! $this->filled('request_type') && $this->filled('type')) {
            $values['request_type'] = $this->input('type');
        }
        if (! $this->filled('requester_name') && $this->filled('requested_by')) {
            $values['requester_name'] = $this->input('requested_by');
        }
        if (! $this->filled('approver_name') && $this->filled('approver')) {
            $values['approver_name'] = $this->input('approver');
        }
        if ($values !== []) {
            $this->merge($values);
        }
    }

    public function rules(): array
    {
        $partial = $this->isMethod('PATCH');
        $required = $partial ? 'sometimes' : 'required';

        return [
            'title' => [$required, 'string', 'max:180'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:100'],
            'request_type' => ['sometimes', 'nullable', 'string', 'max:120'],
            'type' => ['sometimes', 'nullable', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'priority' => ['sometimes', 'nullable', 'string', Rule::in(['low', 'normal', 'medium', 'high', 'urgent'])],
            'requested_by' => ['sometimes', 'nullable', 'string', 'max:180'],
            'requester_name' => ['sometimes', 'nullable', 'string', 'max:180'],
            'requested_by_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'approver' => ['sometimes', 'nullable', 'string', 'max:180'],
            'approver_name' => ['sometimes', 'nullable', 'string', 'max:180'],
            'approver_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'entity' => ['sometimes', 'nullable', 'string', 'max:180'],
            'entity_type' => ['sometimes', 'nullable', 'string', 'max:120'],
            'entity_uuid' => ['sometimes', 'nullable', 'uuid'],
            'source' => ['sometimes', 'nullable', 'string', 'max:120'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'record_date' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'string', Rule::in(Approval::STATUSES)],
            'decision_note' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'expected_version' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'metadata' => ['sometimes', 'nullable', 'array', 'max:50'],
        ];
    }

    public function approvalPayload(): array
    {
        return $this->validated();
    }
}
