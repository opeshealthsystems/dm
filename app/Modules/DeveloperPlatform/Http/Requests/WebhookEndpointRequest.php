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
                $host = parse_url((string) $value, PHP_URL_HOST) ?: '';
                $isIp = filter_var($host, FILTER_VALIDATE_IP);
                $privateIp = $isIp && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
                if (app()->isProduction() && (! str_starts_with((string) $value, 'https://') || $privateIp || $host === 'localhost')) {
                    $fail('The webhook URL must be a public https URL.');
                }
            }],
            'events' => [$req, 'array', 'min:1'],
            'events.*' => ['string', Rule::in(WebhookEndpoint::EVENTS)],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
