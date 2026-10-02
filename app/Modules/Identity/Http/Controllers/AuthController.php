<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
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

        return $this->tokenResponse($user, 201);
    }

    /**
     * Log in with email and password and receive an access token (first-party apps).
     *
     * Third-party integrations should use the OAuth 2.0 flows under `/oauth/*`.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => ['These credentials do not match our records.']]);
        }

        if ($user->isSuspended()) {
            return response()->json(['message' => 'This account is suspended.'], 403);
        }

        return $this->tokenResponse($user);
    }

    /** The authenticated user. Requires `profile`. */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
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
