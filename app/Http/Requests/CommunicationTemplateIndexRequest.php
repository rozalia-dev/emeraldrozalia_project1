<?php

namespace App\Http\Requests;

use App\Services\CommunicationTemplateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommunicationTemplateIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(CommunicationTemplateService::STATUSES)],
            'category' => ['sometimes', 'nullable', 'string', 'max:120'],
            'language' => ['sometimes', 'nullable', 'string', 'max:80'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
