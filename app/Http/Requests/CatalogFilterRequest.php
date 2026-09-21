<?php

namespace App\Http\Requests;

use App\Support\CatalogCounties;
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
        foreach (['q', 'country', 'county', 'club', 'subcategory', 'min_price', 'max_price', 'availability', 'sort'] as $field) {
            if ($this->has($field)) {
                $normalized[$field] = trim((string) $this->input($field));
            }
        }
        if (isset($normalized['country'])) {
            $normalized['country'] = strtoupper($normalized['country']);
        }
        if (isset($normalized['county'])) {
            $normalized['county'] = strtoupper($normalized['county']);
        }
        if (isset($normalized['subcategory'])
            && $normalized['subcategory'] !== ''
            && ! str_contains($normalized['subcategory'], ':')
            && preg_match('/^[a-z0-9][a-z0-9-]*$/i', $normalized['subcategory'])) {
            // Backward compatibility for older storefront links that passed the
            // child category slug directly (for example ?subcategory=heritage).
            // The current hierarchy uses an explicit category: prefix so product
            // types and category descendants cannot collide.
            $normalized['subcategory'] = 'category:'.$normalized['subcategory'];
        }
        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:3', Rule::exists('catalog_countries', 'code')->where('is_active', true)],
            'county' => [
                'nullable',
                'string',
                'max:16',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! filled($value)) {
                        return;
                    }

                    $country = strtoupper(trim((string) $this->input('country', '')));
                    if ($country === '' || ! CatalogCounties::isValid($country, (string) $value)) {
                        $fail('The selected county does not belong to the selected country.');
                    }
                },
            ],
            'club' => ['nullable', 'string', 'max:220', 'regex:/^[a-z0-9][a-z0-9-]*$/i'],
            'subcategory' => ['nullable', 'string', 'max:200', 'regex:/^(?:type|category):[a-z0-9][a-z0-9-]*$/i'],
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
