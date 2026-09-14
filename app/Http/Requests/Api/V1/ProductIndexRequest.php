<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Throwable;

class ProductIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'string', Rule::in(['newest', 'price_low', 'price_high', 'name'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
            'min_price' => ['nullable', 'numeric', 'regex:/^\d+(?:\.\d+)?$/', 'min:0', 'max:999999.99'],
            'max_price' => ['nullable', 'numeric', 'regex:/^\d+(?:\.\d+)?$/', 'min:0', 'max:999999.99', 'gte:min_price'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalised = [];

        foreach (['min_price', 'max_price'] as $field) {
            $value = $this->input($field);
            if (! is_scalar($value) || trim((string) $value) === '') {
                continue;
            }

            try {
                $normalised[$field] = Money::round((string) $value);
            } catch (Throwable) {
                // Leave malformed values for the regex/numeric validator to reject.
            }
        }

        if ($normalised !== []) {
            $this->merge($normalised);
        }
    }
}
