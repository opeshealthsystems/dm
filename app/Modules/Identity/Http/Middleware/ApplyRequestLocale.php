<?php

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** API routes have no session, so take the language from `Accept-Language` (also used for e-mails). */
class ApplyRequestLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('locales.supported'));
        foreach ($request->getLanguages() as $lang) {
            $lang = strtolower(str_replace('_', '-', $lang));
            $base = explode('-', $lang)[0];
            $pick = in_array($lang, $supported, true) ? $lang : ($base === 'zh' ? 'zh-hans' : (in_array($base, $supported, true) ? $base : null));
            if ($pick) {
                app()->setLocale($pick);
                break;
            }
        }

        return $next($request);
    }
}
