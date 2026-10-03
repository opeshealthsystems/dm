<?php

namespace App\Modules\Admin\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryRequest extends AdminFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('slug')) {
            $this->merge(['slug' => Str::slug((string) $this->input('slug'))]);
        } elseif ($this->isMethod('POST') && $this->filled('name')) {
            $this->merge(['slug' => Str::slug((string) $this->input('name'))]);
        }
    }

    public function rules(): array
    {
        $category = $this->route('category');
        $id = is_object($category) ? $category->id : $category;
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$presence, 'string', 'min:1', 'max:120'],
            'slug' => [$presence, 'string', 'min:1', 'max:190', Rule::unique('categories', 'slug')->ignore($id)],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
        ];
    }
}
