<?php

namespace App\Modules\DeveloperPlatform\Http\Requests;

use App\Modules\DeveloperPlatform\Models\ApiKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', Rule::in(ApiKey::ALLOWED_SCOPES)],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
