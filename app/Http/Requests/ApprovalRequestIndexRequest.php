<?php

namespace App\Http\Requests;

use App\Models\Approval;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApprovalRequestIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'type' => ['sometimes', 'nullable', 'string', 'max:120'],
            'request_type' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(Approval::STATUSES)],
            'priority' => ['sometimes', 'nullable', 'string', Rule::in(['low', 'normal', 'medium', 'high', 'urgent'])],
            'requested_by' => ['sometimes', 'nullable', 'string', 'max:180'],
            'approver' => ['sometimes', 'nullable', 'string', 'max:180'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date'],
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
