<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99', 'gte:min_price'],
        ];
    }
}
