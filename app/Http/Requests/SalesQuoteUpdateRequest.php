<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesQuoteUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('line_items') && is_string($this->input('line_items'))) {
            $decoded = json_decode((string) $this->input('line_items'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['line_items' => $decoded]);
            }
        }

        foreach (['currency_code', 'notes'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $this->merge([$field => trim((string) $this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'line_items' => ['sometimes', 'array', 'max:100'],
            'line_items.*.product_id' => ['required', 'integer', 'min:1', 'exists:products,id'],
            'line_items.*.variant_id' => ['nullable', 'integer', 'min:1', 'exists:product_variants,id'],
            'line_items.*.product_variant_id' => ['nullable', 'integer', 'min:1', 'exists:product_variants,id'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'line_items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'line_items.*.options' => ['nullable', 'array', 'max:20'],
            'shipping' => ['sometimes', 'numeric', 'min:0', 'max:999999999.99'],
            'discount' => ['sometimes', 'numeric', 'min:0', 'max:999999999.99'],
            'currency_code' => ['sometimes', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'exchange_rate' => ['sometimes', 'numeric', 'gt:0', 'max:999999999.99999999'],
            'customer_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'expected_version' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
