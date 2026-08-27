<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The whole locale switch: store the choice, bounce back to where the
 * viewer was. App\Http\Middleware\SetLocale is what makes the stored
 * choice apply to the next request; this controller only ever writes it.
 */
class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        if (! array_key_exists($locale, config('lab.locales', []))) {
            throw new NotFoundHttpException;
        }

        $request->session()->put('locale', $locale);

        return redirect()->back();
    }
}
