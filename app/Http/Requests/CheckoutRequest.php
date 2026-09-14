<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'address_id' => ['nullable', 'integer', 'exists:addresses,id'],
            'name' => ['nullable', 'string', 'max:120', 'required_without:address_id'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'line1' => ['nullable', 'string', 'max:180', 'required_without:address_id'],
            'line2' => ['nullable', 'string', 'max:180'],
            'city' => ['nullable', 'string', 'max:120', 'required_without:address_id'],
            'county' => ['nullable', 'string', 'max:120'],
            'postcode' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'size:2', 'required_without:address_id'],
            'shipping_method' => ['nullable', 'string', 'max:80'],
            'payment_method' => ['required', 'in:cod,bank_transfer,manual'],
            'discount_code' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ];
    }

    public function idempotencyKey(): string
    {
        $header = $this->header('Idempotency-Key');
        $candidate = is_string($header) && trim($header) !== ''
            ? trim($header)
            : trim((string) $this->input('idempotency_key', ''));

        if ($candidate === '') {
            $candidate = trim((string) session('checkout_idempotency_key', ''));
        }
        if ($candidate === '') {
            $candidate = (string) Str::uuid();
        }

        if (preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $candidate) !== 1) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Use up to 100 letters, numbers, dots, underscores, colons or hyphens.',
            ]);
        }

        session(['checkout_idempotency_key' => $candidate]);

        return $candidate;
    }
}
