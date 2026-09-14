<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesCommunication;
use Illuminate\Foundation\Http\FormRequest;

class ApprovalDecisionRequest extends FormRequest
{
    use AuthorizesCommunication;
    public function authorize(): bool
    {
        return $this->communicationAuthorized('approve');
    }

    public function rules(): array
    {
        return [
            'decision_note' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'expected_version' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
