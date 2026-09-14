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
            'assigned_to' => ['sometimes', 'nullable', 'integer', $this->companyAdminRule()],
            'follow_up_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    private function companyAdminRule(): Rule
    {
        return Rule::exists('users', 'id')->where(function ($query): void {
            $query->where('is_admin', true)
                ->where('status', 'active')
                ->whereNull('locked_at');

            $companyId = session('company_id');
            if ($companyId) {
                $query->whereExists(function ($membership) use ($companyId): void {
                    $membership->selectRaw('1')
                        ->from('company_user')
                        ->whereColumn('company_user.user_id', 'users.id')
                        ->where('company_user.company_id', (int) $companyId);
                });
            }
        });
    }
}
