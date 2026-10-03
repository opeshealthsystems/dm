<?php

namespace App\Modules\Escrow\Http\Requests;

use App\Modules\Escrow\Models\DisputeMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisputeMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'kind' => ['sometimes', Rule::in(DisputeMessage::KINDS)],
        ];
    }
}
