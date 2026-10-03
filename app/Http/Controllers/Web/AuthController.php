<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Actions\Authenticator;
use App\Modules\Identity\Exceptions\AccountSafetyException;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** Session login for the web dashboards. Validation rules are shared with the API. */
class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request, Authenticator $authenticator): RedirectResponse
    {
        $credentials = $request->validated();

        try {
            $user = $authenticator->checkPassword($credentials['email'], $credentials['password']);
        } catch (AccountSafetyException $e) {
            throw ValidationException::withMessages(['email' => [$e->getMessage()]]);
        }

        if ($user->hasTwoFactorEnabled()) {
            // Not signed in yet: park the user id in the session and ask for the second factor.
            $request->session()->regenerate();
            $request->session()->put('login.2fa', [
                'id' => $user->id,
                'remember' => $request->boolean('remember'),
                'expires' => now()->addMinutes(5)->getTimestamp(),
            ]);

            return redirect()->route('two-factor.challenge');
        }

        $authenticator->complete($user);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = new User(collect($data)->except('role')->all());
        $user->role = $data['role'] ?? User::ROLE_BUYER;
        $user->save();
        $user->sendEmailVerificationNotification();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
