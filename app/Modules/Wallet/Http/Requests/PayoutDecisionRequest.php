<?php

namespace App\Modules\Wallet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Body for admin approve / reject / mark-paid. `note` is required to reject, `txid` to mark paid. */
class PayoutDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();

        return [
            'note' => [$action === 'reject' ? 'required' : 'nullable', 'string', 'max:2000'],
            'txid' => [$action === 'markPaid' ? 'required' : 'nullable', 'string', 'max:128'],
        ];
    }
}
