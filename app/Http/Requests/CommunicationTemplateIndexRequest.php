<?php

namespace App\Http\Requests;

use App\Services\CommunicationTemplateService;
use App\Http\Requests\Concerns\AuthorizesCommunication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommunicationTemplateIndexRequest extends FormRequest
{
    use AuthorizesCommunication;
    public function authorize(): bool
    {
        return $this->communicationAuthorized('view');
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
