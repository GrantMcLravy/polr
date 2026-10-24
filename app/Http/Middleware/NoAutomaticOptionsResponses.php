<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

/**
 * Answer OPTIONS requests with 405, as Polr 2.x did.
 *
 * Laravel's router answers OPTIONS for any path that has routes for other
 * methods with an automatic 200 and an Allow header. Polr defines no OPTIONS
 * routes and Lumen returned 405 Method Not Allowed (or 404 for unknown paths).
 */
class NoAutomaticOptionsResponses
{
    public function __construct(private Router $router) {}

    public function handle(Request $request, Closure $next) {
        if ($request->isMethod('OPTIONS')) {
            // Throws NotFoundHttpException for unknown paths; any match for
            // OPTIONS is the router's automatic response.
            $this->router->getRoutes()->match($request);
            throw new MethodNotAllowedHttpException([]);
        }

        return $next($request);
    }
}
