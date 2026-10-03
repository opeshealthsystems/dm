<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'handle' => ['sometimes', 'string', 'alpha_dash', 'max:64', Rule::unique('users', 'handle')->ignore($this->user()?->id)],
            // Shop fields are only applied for vendors (see AuthController::updateMe).
            'shop_name' => ['sometimes', 'string', 'max:255'],
            'shop_description' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
