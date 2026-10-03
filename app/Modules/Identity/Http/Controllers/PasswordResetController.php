<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\PasswordReset;
use App\Modules\Identity\Http\Requests\ForgotPasswordRequest;
use App\Modules\Identity\Http\Requests\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    public function __construct(private readonly PasswordReset $passwords)
    {
    }

    /**
     * Request a password reset link.
     *
     * Always answers 200 with the same message, whether or not the e-mail belongs to an account,
     * so it cannot be used to find out who is registered. The link is valid for 60 minutes and
     * works once. Rate limited per IP and per e-mail.
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwords->request($request->validated('email'));

        return response()->json(['message' => __('security.forgot.sent')]);
    }

    /**
     * Set a new password with the token from the e-mail.
     *
     * A wrong, expired (60 minutes) or already used token gives 422 `invalid_token`. On success
     * the password changes and every access token, refresh token and browser session of the
     * account is revoked, so the user must log in again everywhere.
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwords->reset($request->validated('email'), $request->validated('token'), $request->validated('password'));

        return response()->json(['message' => __('security.reset.done')]);
    }
}
