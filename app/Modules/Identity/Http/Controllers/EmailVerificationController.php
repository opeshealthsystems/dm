<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * Resend the e-mail verification link. Requires `profile`.
     *
     * Rate limited (3 per 10 minutes). Does nothing if the address is already verified.
     * Unverified accounts can browse and buy but cannot request payouts or publish products.
     */
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => __('security.verify.already')]);
        }
        $user->sendEmailVerificationNotification();

        return response()->json(['message' => __('security.verify.sent')], 202);
    }
}
