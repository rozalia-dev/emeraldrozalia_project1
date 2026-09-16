<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesCommunication;
use App\Services\CommunicationTemplateAttachmentService;
use App\Services\CommunicationTemplateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommunicationTemplateRequest extends FormRequest
{
    use AuthorizesCommunication;

    public function authorize(): bool
    {
        return $this->communicationAuthorized($this->isMethod('POST') ? 'create' : 'edit');
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        if (! $this->filled('title') && $this->filled('name')) {
            $values['title'] = $this->input('name');
        }

        if ($this->has('channel')) {
            $values['channel'] = strtolower(trim((string) $this->input('channel')));
        } elseif ($this->isMethod('POST')) {
            $values['channel'] = 'email';
        }

        if (! $this->has('status') && $this->isMethod('POST')) {
            $values['status'] = 'draft';
        }

        if ($this->has('attachment_mode')) {
            $values['attachment_mode'] = strtolower(trim((string) $this->input('attachment_mode')));
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
            'subject' => [$required, 'string', 'max:250'],
            'body' => [$required, 'string', 'max:10000'],
            'channel' => [$required, 'string', Rule::in(['email'])],
            'status' => [$required, 'string', Rule::in(CommunicationTemplateService::STATUSES)],
            'category' => ['sometimes', 'nullable', 'string', 'max:120'],
            'language' => ['sometimes', 'nullable', 'string', 'max:80'],
            'variables' => ['sometimes', 'nullable', 'array', 'max:30'],
            'expected_version' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'allowed_roles' => ['sometimes', 'nullable', 'array', 'max:30'],
            'allowed_roles.*' => ['string', 'max:120'],
            'attachment_mode' => ['sometimes', 'nullable', 'string', Rule::in(CommunicationTemplateAttachmentService::MODES)],
            'attachment_label' => ['sometimes', 'nullable', 'string', 'max:180'],
            'attachment_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'attachment_file' => [
                'sometimes',
                'nullable',
                'file',
                'max:15360',
                'mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png,webp,zip',
            ],
            'remove_attachment_file' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    public function templatePayload(): array
    {
        $validated = $this->validated();
        $payload = [];

        foreach (['title', 'subject', 'body', 'channel', 'status', 'expected_version'] as $key) {
            if (array_key_exists($key, $validated)) {
                $payload[$key] = $validated[$key];
            }
        }

        if (array_key_exists('title', $payload)) {
            $payload['name'] = $payload['title'];
            unset($payload['title']);
        }

        $variables = is_array($validated['variables'] ?? null) ? $validated['variables'] : [];
        foreach (['category', 'language'] as $key) {
            if (array_key_exists($key, $validated)) {
                $variables[$key] = $validated[$key];
            }
        }
        if ($variables !== [] || array_key_exists('variables', $validated) || array_key_exists('category', $validated) || array_key_exists('language', $validated)) {
            ksort($variables);
            $payload['variables'] = $variables;
        }

        return $payload;
    }
}
