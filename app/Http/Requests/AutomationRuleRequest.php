<?php

namespace App\Http\Requests;

use App\Models\AutomationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AutomationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'event' => strtolower(trim((string) $this->input('event'))),
            'actions' => is_string($this->input('actions')) ? trim($this->input('actions')) : $this->input('actions'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'event' => ['required', 'string', Rule::in(AutomationRule::EVENTS)],
            'actions' => ['required', 'string', 'max:500'],
            'enabled' => ['nullable', 'boolean'],
        ];
    }
}
