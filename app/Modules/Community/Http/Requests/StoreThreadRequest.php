<?php

namespace App\Modules\Community\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // CommunityThreadPolicy::create is applied in the controller
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:' . config('community.title_max')],
            'body' => ['required', 'string', 'min:2', 'max:' . config('community.body_max')],
            // Public slug of one of the author's own active products.
            'product' => ['nullable', 'string', 'max:190'],
        ];
    }
}
