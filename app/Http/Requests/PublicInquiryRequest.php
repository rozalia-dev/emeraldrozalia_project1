<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicInquiryRequest extends FormRequest
{
    private const TYPES = ['contact', 'franchise', 'careers', 'corporate-orders', 'bulk-orders'];

    private const MEETING_TIMES = ['13:00', '13:15', '13:30', '13:45'];

    private const MEETING_TYPES = ['in_person', 'microsoft_teams', 'google_meet'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $fields = [
            'type', 'name', 'email', 'phone', 'company', 'country', 'subject', 'message',
            'product_interest', 'branding_requirement', 'preferred_location', 'investment_range',
            'business_experience', 'opening_timeline', 'meeting_type',
        ];
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

            // Structured Corporate/Bulk intake. These are optional so the approved
            // public reference forms remain backwards compatible; when supplied
            // they become authoritative quote metadata rather than free-text only.
            'product_interest' => ['nullable', 'string', 'max:180'],
            'estimated_quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'branding_requirement' => ['nullable', 'string', 'max:180'],
            'required_by' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],

            // Structured Franchise application data. Existing forms that use the
            // Company/Location and Message fields are still mapped server-side.
            'preferred_location' => ['nullable', 'string', 'max:180'],
            'investment_range' => ['nullable', 'string', 'max:180'],
            'business_experience' => ['nullable', 'string', 'max:3000'],
            'opening_timeline' => ['nullable', 'string', 'max:180'],

            'meeting_date' => ['nullable', 'required_with:meeting_time,meeting_type', 'date_format:Y-m-d', 'after_or_equal:today'],
            'meeting_time' => ['nullable', 'required_with:meeting_date,meeting_type', 'date_format:H:i', Rule::in(self::MEETING_TIMES)],
            'meeting_type' => ['nullable', 'required_with:meeting_date,meeting_time', 'string', Rule::in(self::MEETING_TYPES)],
            'idempotency_key' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ];
    }
}
