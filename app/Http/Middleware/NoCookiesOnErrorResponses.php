<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Do not send cookies with responses rendered from an exception.
 *
 * Under Lumen, an exception skipped the rest of the middleware stack, so
 * error responses (404 pages, API errors, CSRF failures...) never carried the
 * session or XSRF-TOKEN cookie; the session itself was still saved. Laravel
 * renders exceptions inside the stack, letting the session and CSRF middleware
 * attach their cookies. This middleware runs outside them and removes those
 * cookies again.
 */
class NoCookiesOnErrorResponses
{
    public function handle(Request $request, Closure $next) {
        $response = $next($request);
        $exception = $response->exception ?? null;

        // Validation failures and HttpResponseExceptions were turned into
        // responses inside Lumen's controller dispatch, so they did pass back
        // through the middleware and got cookies.
        if ($exception !== null
                && ! $exception instanceof ValidationException
                && ! $exception instanceof HttpResponseException) {
            foreach ($response->headers->getCookies() as $cookie) {
                $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
            }
        }

        return $response;
    }
}
