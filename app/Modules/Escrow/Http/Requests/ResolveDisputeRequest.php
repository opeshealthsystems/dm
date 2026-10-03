<?php

namespace App\Modules\Escrow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::in(['buyer', 'vendor'])],
            'resolution' => ['required', 'string', 'min:3', 'max:5000'], // legacy: "Resolution note required."
        ];
    }
}
