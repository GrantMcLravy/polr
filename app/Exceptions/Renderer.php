<?php

namespace App\Exceptions;

use App\Exceptions\Api\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Renders exceptions the way Polr 2.x's Lumen exception handler did.
 *
 * Returning null leaves the exception to Laravel's default rendering, which
 * (with debug off) shows resources/views/errors/500.blade.php with status 500.
 */
class Renderer {
    public function render(Throwable $e, Request $request) {
        if ($e instanceof ValidationException) {
            // Lumen 5.1's validate() answered AJAX/JSON requests with the bare
            // error bag, not Laravel's {"message", "errors"} envelope. Other
            // requests are redirected back with errors by Laravel, as before.
            if ($request->ajax() || $request->wantsJson()) {
                return new JsonResponse($e->errors(), 422);
            }
            return null;
        }

        if (config('app.debug')) {
            // With debug on, Lumen fell through to its debug error page.
            return null;
        }

        // The layouts used by the error pages expect $errors to be shared. It
        // is, unless the exception happened before ShareErrorsFromSession ran.
        if (! array_key_exists('errors', view()->getShared())) {
            view()->share('errors', new ViewErrorBag);
        }

        if ($e instanceof HttpException && $e->getPrevious() instanceof TokenMismatchException) {
            // Laravel turns a CSRF failure into an HTTP 419. Lumen did not treat
            // it as an HTTP exception, so it was a 500.
            return response(view('errors.500'), 500);
        }

        if ($e instanceof NotFoundHttpException) {
            // Handle 404 exceptions
            if (env('SETTING_REDIRECT_404')) {
                // Redirect 404s to SETTING_INDEX_REDIRECT
                return redirect()->to(env('SETTING_INDEX_REDIRECT'));
            }
            // Otherwise, show a nice error page
            return response(view('errors.404'), 404);
        }

        if ($e instanceof HttpException) {
            // Handle HTTP exceptions thrown by public-facing controllers
            $status_code = $e->getStatusCode();
            $status_message = $e->getMessage();

            if ($e instanceof MethodNotAllowedHttpException) {
                // Lumen's 405s had no message; Laravel's names the route and
                // its allowed methods.
                $status_message = '';
            }

            if ($status_code == 500) {
                // Render a nice error page for 500s
                return response(view('errors.500'), 500);
            }

            // If not 500, render generic page
            return response(
                view('errors.generic', [
                    'status_code' => $status_code,
                    'status_message' => $status_message
                ]), $status_code);
        }

        if ($e instanceof ApiException) {
            // Handle HTTP exceptions thrown by API controllers
            $status_code = $e->getCode();
            $encoded_status_message = $e->getEncodedErrorMessage();
            if ($e->response_type == 'json') {
                return response($encoded_status_message, $status_code)
                    ->header('Content-Type', 'application/json')
                    ->header('Access-Control-Allow-Origin', '*');
            }

            return response($encoded_status_message, $status_code)
                ->header('Content-Type', 'text/plain')
                ->header('Access-Control-Allow-Origin', '*');
        }

        return null;
    }
}
