<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

class VerifyCsrfToken extends ValidateCsrfToken
{
    /**
     * Exclude the public API from CSRF protection.
     *
     * Excluded paths (see bootstrap/app.php) skip the middleware entirely, as
     * in Polr 2.x, so API responses do not get an XSRF-TOKEN cookie. Laravel's
     * own middleware would still add one.
     */
    public function handle($request, Closure $next) {
        if ($this->inExceptArray($request)) {
            // Exclude public API from CSRF protection
            // but do not exclude private API endpoints
            return $next($request);
        }

        return parent::handle($request, $next);
    }

    /**
     * Laravel 13 also accepts requests whose Sec-Fetch-Site header says
     * "same-origin" without a token. Polr 2.x always required the token.
     */
    protected function hasValidOrigin($request) {
        return false;
    }
}
