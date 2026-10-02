<?php

namespace App\Modules\Reputation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ReviewPolicy is applied in the controller
    }

    public function rules(): array
    {
        return ['reply' => ['required', 'string', 'max:2000']];
    }
}
