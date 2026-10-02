<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization is done by ProductPolicy in the controller
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'price_cents' => [$required, 'integer', 'min:1', 'max:100000000'],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'stock' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['sometimes', Rule::in([Product::STATUS_DRAFT, Product::STATUS_ACTIVE, Product::STATUS_ARCHIVED])],
        ];
    }
}
