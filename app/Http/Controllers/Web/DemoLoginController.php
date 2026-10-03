<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Local-only convenience: sign in as one of the seeded demo accounts with one click.
 * Hard-gated by environment, not just by config, so it cannot be switched on in production.
 */
class DemoLoginController extends Controller
{
    public static function enabled(): bool
    {
        return config('demo.enabled') === true && app()->environment('local');
    }

    public function __invoke(Request $request, string $role): RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        $email = config("demo.accounts.$role");
        abort_if($email === null, 404);

        $user = User::where('email', $email)->first();
        abort_if($user === null || $user->isSuspended() || $user->hasTwoFactorEnabled(), 404);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
