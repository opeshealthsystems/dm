<?php

namespace App\Modules\Escrow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeeTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64'],
            'min_sales_cents' => ['required', 'integer', 'min:0', 'unique:vendor_fee_tiers,min_sales_cents'],
            'commission_bps' => ['required', 'integer', 'min:0', 'max:5000'], // 0 - 50%
        ];
    }
}
