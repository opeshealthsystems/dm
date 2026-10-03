<?php

namespace App\Modules\DeveloperPlatform\Http\Requests;

use App\Modules\DeveloperPlatform\Models\WebhookEndpoint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebhookEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $req = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'url' => [$req, 'url:https,http', 'max:500', function ($attr, $value, $fail) {
                if (\App\Modules\DeveloperPlatform\Support\WebhookUrlGuard::violation((string) $value) !== null) {
                    $fail('The webhook URL must be a public https URL.');
                }
            }],
            'events' => [$req, 'array', 'min:1'],
            'events.*' => ['string', Rule::in(WebhookEndpoint::EVENTS)],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
