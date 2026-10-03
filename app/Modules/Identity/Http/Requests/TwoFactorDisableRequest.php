<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Password plus a TOTP code or recovery code. */
class TwoFactorDisableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'max:255'],
            'code' => ['required_without:recovery_code', 'nullable', 'string', 'max:16'],
            'recovery_code' => ['required_without:code', 'nullable', 'string', 'max:32'],
        ];
    }
}
