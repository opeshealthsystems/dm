<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Actions\Authenticator;
use App\Modules\Identity\Http\Requests\ChangePasswordRequest;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use App\Modules\Identity\Http\Requests\UpdateProfileRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly Authenticator $authenticator)
    {
    }

    /**
     * Register a buyer or vendor account.
     *
     * Admin accounts cannot be created through the API. Returns an access token whose
     * scopes are limited to what the role allows.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = new User(collect($data)->except('role')->all());
        $user->role = $data['role'] ?? User::ROLE_BUYER;
        $user->save();
        $user->sendEmailVerificationNotification();

        return $this->tokenResponse($user, 201);
    }

    /**
     * Log in with email and password and receive an access token (first-party apps).
     *
     * When the account has two-factor authentication enabled, also send `code` (a 6-digit
     * authenticator code) or `recovery_code`; without it the response is 422 with
     * `code: two_factor_required`. Five failed attempts in 15 minutes lock the account
     * for the rest of the window (429, `Retry-After`).
     *
     * Third-party integrations should use the OAuth 2.0 flows under `/oauth/*`.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authenticator->checkPassword($request->validated('email'), $request->validated('password'));

        if ($user->hasTwoFactorEnabled()) {
            $code = $request->validated('code');
            $recovery = $request->validated('recovery_code');
            if (blank($code) && blank($recovery)) {
                return response()->json([
                    'message' => __('security.login.code_required'),
                    'code' => 'two_factor_required',
                    'two_factor_required' => true,
                ], 422);
            }
            $this->authenticator->checkSecondFactor($user, $code, $recovery);
        }
        $this->authenticator->complete($user);

        return $this->tokenResponse($user);
    }

    /** The authenticated user. Requires `profile`. */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Update your profile (name, handle, shop name and description). Requires `profile`.
     *
     * Only the fields you send are changed. Shop fields are ignored for buyers.
     */
    public function updateMe(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $data = $request->validated();
        if ($user->role !== User::ROLE_VENDOR) {
            unset($data['shop_name'], $data['shop_description']);
        }
        $user->fill($data)->save();

        return new UserResource($user->refresh());
    }

    /**
     * Change your password. Requires `profile`.
     *
     * Needs the current password and a new one (confirmed, 10+ characters, letters and numbers).
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->password = $request->validated('password');
        $user->save();

        return response()->json(['message' => 'Password updated.']);
    }

    /** Revoke the current access token. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->revoke();

        return response()->json(null, 204);
    }

    private function tokenResponse(User $user, int $status = 200): JsonResponse
    {
        $token = $user->createToken('api', $user->allowedScopes());

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token->accessToken,
            'scopes' => $user->allowedScopes(),
            'user' => new UserResource($user),
        ], $status);
    }
}
