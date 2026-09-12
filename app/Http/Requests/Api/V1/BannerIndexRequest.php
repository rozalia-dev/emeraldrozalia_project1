<?php

namespace App\Http\Requests\Api\V1;

use App\Services\BannerDashboardService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BannerIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'position' => ['nullable', 'string', Rule::in(BannerDashboardService::POSITIONS)],
            'device' => ['nullable', 'string', Rule::in(BannerDashboardService::DEVICES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ];
    }
}
