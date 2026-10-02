<?php

namespace App\Modules\Identity\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'handle' => ['nullable', 'string', 'alpha_dash', 'max:64', 'unique:users,handle'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(10)->letters()->numbers()],
            // admin is deliberately not accepted here
            'role' => ['sometimes', Rule::in([User::ROLE_BUYER, User::ROLE_VENDOR])],
            'shop_name' => ['required_if:role,vendor', 'nullable', 'string', 'max:255'],
            'shop_description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
