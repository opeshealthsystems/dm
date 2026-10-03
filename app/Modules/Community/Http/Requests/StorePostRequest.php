<?php

namespace App\Modules\Community\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Used for new replies and for edits. */
class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policies are applied in the controller
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'min:2', 'max:' . config('community.body_max')]];
    }
}
