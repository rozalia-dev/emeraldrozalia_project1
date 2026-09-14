<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesCommunication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommunicationConversationUpdateRequest extends FormRequest
{
    use AuthorizesCommunication;
    public function authorize(): bool
    {
        return $this->communicationAuthorized('edit');
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::in(['new', 'open', 'pending', 'closed'])],
            'priority' => ['sometimes', 'required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'assigned_to' => ['sometimes', 'nullable', 'integer', $this->companyAdminRule()],
            'follow_up_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    private function companyAdminRule()
    {
        return Rule::exists('users', 'id')->where(function ($query): void {
            $query->where('is_admin', true);
        });
    }
}
