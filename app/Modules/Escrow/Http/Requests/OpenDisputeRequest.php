<?php

namespace App\Modules\Escrow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Body for opening a dispute. Authorization (party to the order) is enforced by DisputePolicy::open. */
class OpenDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:10', 'max:5000']];
    }
}
