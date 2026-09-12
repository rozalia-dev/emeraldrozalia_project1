<?php

namespace App\Http\Requests;

use App\Services\CommunicationTemplateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommunicationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
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
