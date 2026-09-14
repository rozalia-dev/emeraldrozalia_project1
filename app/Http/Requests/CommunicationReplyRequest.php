<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesCommunication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommunicationReplyRequest extends FormRequest
{
    use AuthorizesCommunication;
    public function authorize(): bool
    {
        return $this->communicationAuthorized('edit');
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
            'mode' => ['sometimes', Rule::in(['reply', 'internal_note'])],
        ];
    }
}
