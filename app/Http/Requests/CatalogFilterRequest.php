<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['q', 'min_price', 'max_price', 'availability', 'sort'] as $field) {
            if ($this->has($field)) {
                $normalized[$field] = trim((string) $this->input($field));
            }
        }
        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable'],
            'category.*' => ['string', 'max:80'],
            'material' => ['nullable'],
            'material.*' => ['string', 'max:80'],
            'colour' => ['nullable'],
            'colour.*' => ['string', 'max:80'],
            'size' => ['nullable'],
            'size.*' => ['string', 'max:80'],
            'min_price' => ['nullable', 'string', 'regex:/^(?:0|[1-9][0-9]{0,8})(?:\.[0-9]{1,2})?$/'],
            'max_price' => ['nullable', 'string', 'regex:/^(?:0|[1-9][0-9]{0,8})(?:\.[0-9]{1,2})?$/'],
            'availability' => ['nullable', Rule::in(['in_stock', 'out_of_stock'])],
            'sort' => ['nullable', Rule::in(['newest', 'price_low', 'price_high', 'name', 'rating', 'popular'])],
            'per_page' => ['nullable', 'integer', Rule::in([12, 24, 36])],
            'sale' => ['nullable', 'boolean'],
        ];
    }
}
