<?php

use App\Exceptions\Renderer;
use App\Http\Middleware\NoAutomaticOptionsResponses;
use App\Http\Middleware\NoCookiesOnErrorResponses;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Middleware\ValidatePathEncoding;
use Illuminate\Http\Middleware\ValidatePostSize;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// No health-check route: Polr owns every top-level path (/{short_url}), so a
// framework route such as /up would shadow a short link.
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Polr 2.x (Lumen) ran cookies, session and CSRF as global middleware,
        // before routing, so every response (including 404s for unknown paths)
        // has a session and a CSRF token. Keep that order rather than Laravel's
        // "web" group.
        //
        // Not included, because Lumen did not have them and they would change
        // request handling: TrimStrings (would trim passwords),
        // ConvertEmptyStringsToNull, and HandleCors (Polr sets
        // Access-Control-Allow-Origin itself and never answered preflights).
        //
        // Trusted proxies come from TRUSTED_PROXIES (config/trustedproxy.php).
        $middleware->use([
            ValidatePathEncoding::class,
            InvokeDeferredCallbacks::class,
            TrustProxies::class,
            PreventRequestsDuringMaintenance::class,
            ValidatePostSize::class,
            NoCookiesOnErrorResponses::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            NoAutomaticOptionsResponses::class,
        ]);

        $middleware->group('web', [
            SubstituteBindings::class,
        ]);

        // The public API is exempt from CSRF; the internal admin AJAX endpoints
        // under /api/v2 are not. (Read by App\Http\Middleware\VerifyCsrfToken,
        // which extends Laravel's CSRF middleware.)
        $middleware->validateCsrfTokens(except: [
            'api/v*/action/*',
            'api/v*/data/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(fn (Throwable $e, $request) => (new Renderer)->render($e, $request));
    })->create();
