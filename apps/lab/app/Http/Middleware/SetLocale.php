<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The console defaults to config('app.locale') — Arabic. A viewer who
 * switches locale (App\Http\Controllers\LocaleController) has that choice
 * stored in the session; this is what makes it stick across requests.
 * Anything not in config('lab.locales') is ignored rather than applied, so
 * a stale or tampered session value can never set an unsupported locale.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');

        if (is_string($locale) && array_key_exists($locale, config('lab.locales', []))) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
