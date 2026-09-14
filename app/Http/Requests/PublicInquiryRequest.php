<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicInquiryRequest extends FormRequest
{
    private const TYPES = ['contact', 'franchise', 'careers', 'corporate-orders', 'bulk-orders'];

    private const MEETING_TIMES = ['09:00', '10:00', '11:00', '14:00', '15:00', '16:00'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $fields = ['type', 'name', 'email', 'phone', 'company', 'country', 'subject', 'message'];
        $normalized = [];
        foreach ($fields as $field) {
            if ($this->has($field)) {
                $normalized[$field] = trim((string) $this->input($field));
            }
        }

        $headerKey = trim((string) $this->header('Idempotency-Key', ''));
        $inputKey = trim((string) $this->input('idempotency_key', ''));
        $normalized['idempotency_key'] = $headerKey !== '' ? $headerKey : ($inputKey !== '' ? $inputKey : null);
        $this->merge($normalized);
    }

    public function rules(): array
    {
        $type = (string) $this->input('type', '');
        $requiresMessage = in_array($type, ['contact', 'franchise', 'corporate-orders', 'bulk-orders'], true);
        $requiresConsent = in_array($type, ['contact', 'franchise'], true);

        return [
            'type' => ['required', 'string', Rule::in(self::TYPES)],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'subject' => ['required_if:type,contact', 'nullable', 'string', 'max:150'],
            'message' => [Rule::requiredIf($requiresMessage), 'nullable', 'string', 'max:5000'],
            'consent' => $requiresConsent ? ['required', 'accepted'] : ['nullable'],
            'meeting_date' => ['nullable', 'required_with:meeting_time', 'date_format:Y-m-d', 'after_or_equal:today'],
            'meeting_time' => ['nullable', 'required_with:meeting_date', 'date_format:H:i', Rule::in(self::MEETING_TIMES)],
            'idempotency_key' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ];
    }
}
