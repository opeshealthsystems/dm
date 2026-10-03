<?php

namespace App\Modules\Wallet\Http\Requests;

use App\Modules\Wallet\Models\PayoutRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => strtoupper((string) $this->input('currency', 'EUR')),
            'destination_address' => trim((string) $this->input('destination_address')),
        ]);
    }

    public function rules(): array
    {
        return [
            'amount_cents' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'method' => ['required', Rule::in(PayoutRequest::METHODS)],
            'destination_address' => ['required', 'string', 'max:120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->has('destination_address') || $v->errors()->has('method')) {
                return;
            }
            $address = $this->input('destination_address');
            $ok = match ($this->input('method')) {
                // Base58 (P2PKH/P2SH) or bech32 (lowercase).
                'bitcoin' => preg_match('/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/', $address) === 1
                    || preg_match('/^bc1[ac-hj-np-z02-9]{11,71}$/', $address) === 1,
                // Standard (95) or integrated (106) Monero address.
                'monero' => preg_match('/^[48][0-9AB][1-9A-HJ-NP-Za-km-z]{93}$/', $address) === 1
                    || preg_match('/^4[0-9AB][1-9A-HJ-NP-Za-km-z]{104}$/', $address) === 1,
                default => false,
            };
            if (! $ok) {
                $v->errors()->add('destination_address', 'The destination address is not a valid ' . $this->input('method') . ' address.');
            }
        });
    }
}
