<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApprovalDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'decision_note' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'expected_version' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
