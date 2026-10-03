<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\TwoFactorAuthentication;
use App\Modules\Identity\Http\Requests\TwoFactorCodeRequest;
use App\Modules\Identity\Http\Requests\TwoFactorDisableRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Authenticator-app (TOTP) two-factor management. All endpoints require `profile`. */
class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorAuthentication $twoFactor)
    {
    }

    /** Two-factor status: `enabled` and how many recovery codes are left. */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'enabled' => $user->hasTwoFactorEnabled(),
            'pending_setup' => ! $user->hasTwoFactorEnabled() && $user->two_factor_secret !== null,
            'recovery_codes_remaining' => $user->hasTwoFactorEnabled() ? $this->twoFactor->remainingRecoveryCodes($user) : 0,
        ]]);
    }

    /**
     * Start setup. Returns the base32 `secret` and an `otpauth_uri` (for authenticator apps / QR
     * generators). Calling it again replaces an unconfirmed secret. 409 if 2FA is already on.
     */
    public function setup(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->twoFactor->begin($request->user())]);
    }

    /**
     * Confirm setup with a code from the app. Turns 2FA on and returns 10 single-use
     * `recovery_codes`. They are shown only this once.
     */
    public function confirm(TwoFactorCodeRequest $request): JsonResponse
    {
        $codes = $this->twoFactor->confirm($request->user(), $request->validated('code'));

        return response()->json(['data' => ['enabled' => true, 'recovery_codes' => $codes]]);
    }

    /** Turn 2FA off. Needs the account `password` and a current `code` (or a `recovery_code`). */
    public function disable(TwoFactorDisableRequest $request): JsonResponse
    {
        $this->twoFactor->disable($request->user(), $request->validated('password'), $request->validated('code'), $request->validated('recovery_code'));

        return response()->json(['data' => ['enabled' => false]]);
    }

    /** Replace all recovery codes. Needs `password` and a current `code`. The old codes stop working. */
    public function recoveryCodes(TwoFactorDisableRequest $request): JsonResponse
    {
        $codes = $this->twoFactor->regenerate($request->user(), $request->validated('password'), $request->validated('code'));

        return response()->json(['data' => ['recovery_codes' => $codes]]);
    }
}
