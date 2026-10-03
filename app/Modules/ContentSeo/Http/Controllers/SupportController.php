<?php

namespace App\Modules\ContentSeo\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ContentSeo\Models\SupportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Support
 */
class SupportController extends Controller
{
    /**
     * Send a support request. Public, rate limited.
     *
     * Fields: `name`, `email`, `subject`, `message` (10-5000 characters). The hidden `website`
     * field is a honeypot: it must stay empty. Returns 201 when the request was received.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['nullable', 'string', 'max:500'],
        ]);

        // Bots fill the hidden field. Answer exactly like a success so they learn nothing.
        if (filled($data['website'] ?? null)) {
            return response()->json(['data' => ['received' => true]], 201);
        }

        $row = new SupportRequest;
        $row->forceFill([
            'user_id' => $request->user('api')?->getKey(),
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'locale' => app()->getLocale(),
        ])->save();

        return response()->json(['data' => ['received' => true]], 201);
    }
}
