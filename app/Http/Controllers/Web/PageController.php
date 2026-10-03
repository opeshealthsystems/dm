<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    /** Send a signed-in user to the right area for their role. */
    public function dashboard(Request $request): RedirectResponse
    {
        return match ($request->user()->role) {
            'admin' => redirect()->route('admin.overview'),
            'vendor' => redirect()->route('seller.overview'),
            default => redirect()->route('buyer.orders'),
        };
    }

    /** Render resources/views/{area}/{page}.blade.php, or a placeholder until it is built. */
    public function show(string $area, string $page)
    {
        $view = "$area.$page";

        return view(view()->exists($view) ? $view : 'dashboard.stub', ['area' => $area, 'page' => $page]);
    }

    /** Switch language, remembered in the session and a cookie. */
    public function locale(Request $request, string $code): RedirectResponse
    {
        abort_unless(array_key_exists($code, config('locales.supported')), 404);
        $request->session()->put('locale', $code);

        $back = url()->previous();
        $sameHost = parse_url($back, PHP_URL_HOST) === $request->getHost();

        return redirect()->to($sameHost ? $back : route('home'))->withCookie(cookie()->forever('locale', $code));
    }
}
