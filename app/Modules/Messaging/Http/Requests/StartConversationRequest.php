<?php

namespace App\Modules\Messaging\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Either link the conversation to one of your orders, or address a user directly.
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'recipient_id' => ['required_without:order_id', 'nullable', 'integer', 'exists:users,id'],
            'subject' => ['nullable', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
