<?php

namespace App\Modules\Escrow\Http\Requests;

use App\Modules\Escrow\Models\VendorFee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVendorFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['fee_type' => ['sometimes', Rule::in(VendorFee::TYPES)]];
    }
}
