<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CommunicationReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
        ];
    }
}
