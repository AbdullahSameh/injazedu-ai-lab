<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * FR-047: the console is Arabic-first, not Arabic-only. These cover the
 * one property worth a test — an unsupported locale value, whether typed
 * into the URL or sitting in a stale/tampered session, is never the one
 * App\Http\Middleware\SetLocale hands to app()->setLocale(). The route
 * test proves the write side; the middleware tests below prove the read
 * side without going through Filament's panel auth, a separate concern.
 */
class LocaleSwitchTest extends TestCase
{
    public function test_switching_to_a_supported_locale_stores_it_in_the_session_and_redirects_back(): void
    {
        $response = $this->from('/admin')->get('/locale/en');

        $response->assertRedirect('/admin');
        $this->assertSame('en', session('locale'));
    }

    public function test_an_unsupported_locale_is_refused_and_never_stored(): void
    {
        $this->get('/locale/fr')->assertNotFound();

        $this->assertNull(session('locale'));
    }

    public function test_the_middleware_applies_a_supported_locale_from_the_session(): void
    {
        $request = Request::create('/admin');
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->put('locale', 'en');

        (new SetLocale)->handle($request, fn () => response(''));

        $this->assertSame('en', app()->getLocale());
    }

    public function test_the_middleware_ignores_an_unsupported_value_planted_in_the_session(): void
    {
        app()->setLocale('ar');

        $request = Request::create('/admin');
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->put('locale', 'fr');

        (new SetLocale)->handle($request, fn () => response(''));

        $this->assertSame('ar', app()->getLocale());
    }
}
