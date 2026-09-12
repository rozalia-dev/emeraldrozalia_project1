<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommunicationConversationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::in(['new', 'open', 'pending', 'closed'])],
            'priority' => ['sometimes', 'required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'assigned_to' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where('is_admin', true)],
            'follow_up_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
