<?php

namespace App\Modules\Community\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // admin scope + policy in the controller
    }

    public function rules(): array
    {
        $id = $this->route('category')?->id;

        return [
            'name' => [$id === null ? 'required' : 'sometimes', 'string', 'max:80'],
            'slug' => ['sometimes', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('community_categories', 'slug')->ignore($id)],
            'description' => ['nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'staff_only' => ['sometimes', 'boolean'],
        ];
    }
}
