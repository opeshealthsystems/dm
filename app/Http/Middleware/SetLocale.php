<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the interface language: ?lang= > session > cookie > Accept-Language > default.
 * Also exposes the text direction (rtl for Arabic) to views.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('locales.supported'));

        $locale = $request->query('lang');
        if ($locale && in_array($locale, $supported, true)) {
            $request->session()->put('locale', $locale);
        }

        $locale = $request->session()->get('locale')
            ?? $request->cookie('locale')
            ?? $this->fromHeader($request, $supported)
            ?? config('locales.default');

        if (! in_array($locale, $supported, true)) {
            $locale = config('locales.default');
        }

        app()->setLocale($locale);
        view()->share('dir', config("locales.supported.$locale.dir", 'ltr'));

        return $next($request);
    }

    private function fromHeader(Request $request, array $supported): ?string
    {
        $preferred = $request->getPreferredLanguage(array_merge($supported, ['zh']));

        return $preferred === 'zh' ? 'zh-hans' : $preferred;
    }
}
