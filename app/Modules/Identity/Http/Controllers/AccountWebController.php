<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Actions\Authenticator;
use App\Modules\Identity\Actions\EmailVerification;
use App\Modules\Identity\Exceptions\AccountSafetyException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** Browser pages for account safety. Data changes go through /api/v1; only login-time steps post here. */
class AccountWebController extends Controller
{
    public function forgot()
    {
        return view('auth.forgot-password');
    }

    public function reset(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    /** Signed e-mail link: verifies the address, then lands on the security page (or login). */
    public function verifyEmail(Request $request, int $id, string $hash, EmailVerification $verification): RedirectResponse
    {
        $target = Auth::check() ? 'account.security' : 'login';
        try {
            $verification->verify($id, $hash);
        } catch (AccountSafetyException $e) {
            return redirect()->route($target)->with('status_error', $e->getMessage());
        }

        return redirect()->route($target)->with('status', __('security.verify.done'));
    }

    public function challenge(Request $request)
    {
        if (! $this->pending($request)) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    /** Second step of the browser login when the account has 2FA enabled. */
    public function submitChallenge(Request $request, Authenticator $authenticator): RedirectResponse
    {
        $pending = $this->pending($request);
        if (! $pending) {
            return redirect()->route('login');
        }
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:16', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:32', 'required_without:code'],
        ]);

        $user = User::find($pending['id']);
        if (! $user || $user->isSuspended() || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget('login.2fa');

            return redirect()->route('login');
        }

        try {
            $authenticator->checkSecondFactor($user, $data['code'] ?? null, $data['recovery_code'] ?? null);
        } catch (AccountSafetyException $e) {
            if ($e->errorCode === 'account_locked') {
                $request->session()->forget('login.2fa');
            }
            throw ValidationException::withMessages(['code' => [$e->getMessage()]]);
        }

        $authenticator->complete($user);
        $request->session()->forget('login.2fa');
        Auth::login($user, (bool) $pending['remember']);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function security(Request $request)
    {
        $area = match ($request->user()->role) {
            User::ROLE_ADMIN => 'admin',
            User::ROLE_VENDOR => 'seller',
            default => 'buyer',
        };

        return view('account.security', ['area' => $area, 'i18nGroups' => ['security']]);
    }

    /** @return array{id: int, remember: bool, expires: int}|null */
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get('login.2fa');
        if (! is_array($pending) || ($pending['expires'] ?? 0) < now()->getTimestamp()) {
            $request->session()->forget('login.2fa');

            return null;
        }

        return $pending;
    }
}
