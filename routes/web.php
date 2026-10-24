<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminPaginationController;
use App\Http\Controllers\AjaxController;
use App\Http\Controllers\Api\ApiAnalyticsController;
use App\Http\Controllers\Api\ApiLinkController;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StaticPageController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\ApiMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Every route runs in the "web" middleware group (session, cookies, CSRF), as
| all routes did under Lumen. The public API (/api/v2/action/*, /api/v2/data/*)
| is excluded from CSRF in bootstrap/app.php.
|
| Laravel matches routes in registration order, so the short-link catch-alls
| /{short_url} and /{short_url}/{secret_key} must stay at the end of this file.
|
*/

/* Optional endpoints */
if (env('POLR_ALLOW_ACCT_CREATION')) {
    Route::get('/signup', [UserController::class, 'displaySignupPage'])->name('signup');
    Route::post('/signup', [UserController::class, 'performSignup'])->name('psignup');
}

/* GET endpoints */

Route::get('/', [IndexController::class, 'showIndexPage'])->name('index');
Route::get('/logout', [UserController::class, 'performLogoutUser'])->name('logout');
Route::get('/login', [UserController::class, 'displayLoginPage'])->name('login');
Route::get('/about-polr', [StaticPageController::class, 'displayAbout'])->name('about');

Route::get('/lost_password', [UserController::class, 'displayLostPasswordPage'])->name('lost_password');
Route::get('/activate/{username}/{recovery_key}', [UserController::class, 'performActivation'])->name('activate');
Route::get('/reset_password/{username}/{recovery_key}', [UserController::class, 'performPasswordReset'])->name('reset_password');

Route::get('/admin', [AdminController::class, 'displayAdminPage'])->name('admin');

Route::get('/setup', [SetupController::class, 'displaySetupPage'])->name('setup');
Route::post('/setup', [SetupController::class, 'performSetup'])->name('psetup');
Route::get('/setup/finish', [SetupController::class, 'finishSetup'])->name('setup_finish');

Route::get('/admin/stats/{short_url}', [StatsController::class, 'displayStats']);

/* POST endpoints */

Route::post('/login', [UserController::class, 'performLogin'])->name('plogin');
Route::post('/shorten', [LinkController::class, 'performShorten'])->name('pshorten');
Route::post('/lost_password', [UserController::class, 'performSendPasswordResetCode'])->name('plost_password');
Route::post('/reset_password/{username}/{recovery_key}', [UserController::class, 'performPasswordReset'])->name('preset_password');

Route::post('/admin/action/change_password', [AdminController::class, 'changePassword'])->name('change_password');

Route::prefix('api/v2')->group(function () {
    /* API internal endpoints (session + CSRF) */
    Route::post('link_avail_check', [AjaxController::class, 'checkLinkAvailability'])->name('api_link_check');
    Route::post('admin/toggle_api_active', [AjaxController::class, 'toggleAPIActive'])->name('api_toggle_api_active');
    Route::post('admin/generate_new_api_key', [AjaxController::class, 'generateNewAPIKey'])->name('api_generate_new_api_key');
    Route::post('admin/edit_api_quota', [AjaxController::class, 'editAPIQuota'])->name('api_edit_quota');
    Route::post('admin/toggle_user_active', [AjaxController::class, 'toggleUserActive'])->name('api_toggle_user_active');
    Route::post('admin/change_user_role', [AjaxController::class, 'changeUserRole'])->name('api_change_user_role');
    Route::post('admin/add_new_user', [AjaxController::class, 'addNewUser'])->name('api_add_new_user');
    Route::post('admin/delete_user', [AjaxController::class, 'deleteUser'])->name('api_delete_user');
    Route::post('admin/toggle_link', [AjaxController::class, 'toggleLink'])->name('api_toggle_link');
    Route::post('admin/delete_link', [AjaxController::class, 'deleteLink'])->name('api_delete_link');
    Route::post('admin/edit_link_long_url', [AjaxController::class, 'editLinkLongUrl'])->name('api_edit_link_long_url');

    Route::get('admin/get_admin_users', [AdminPaginationController::class, 'paginateAdminUsers'])->name('api_get_admin_users');
    Route::get('admin/get_admin_links', [AdminPaginationController::class, 'paginateAdminLinks'])->name('api_get_admin_links');
    Route::get('admin/get_user_links', [AdminPaginationController::class, 'paginateUserLinks'])->name('api_get_user_links');

    // Public API. ApiMiddleware is referenced by class rather than by the Lumen
    // alias "api", because Laravel already defines a middleware group named
    // "api" and group names take precedence over aliases.
    //
    // Endpoints that accept both GET and POST are a single route each, so the
    // route names stay unique (required by route:cache).
    Route::middleware(ApiMiddleware::class)->group(function () {
        /* API shorten endpoints */
        Route::match(['GET', 'POST'], 'action/shorten', [ApiLinkController::class, 'shortenLink'])->name('api_shorten_url');
        Route::post('action/shorten_bulk', [ApiLinkController::class, 'shortenLinksBulk'])->name('api_shorten_url_bulk');

        /* API lookup endpoints */
        Route::match(['GET', 'POST'], 'action/lookup', [ApiLinkController::class, 'lookupLink'])->name('api_lookup_url');

        /* API data endpoints */
        Route::match(['GET', 'POST'], 'data/link', [ApiAnalyticsController::class, 'lookupLinkStats'])->name('api_link_analytics');
    });
});

/* Short links: must be registered last */

// Lumen's router let a static path registered for another method take
// precedence, so GET /shorten (POST-only) was a 405 rather than a short-link
// lookup. Excluding it here keeps that: Laravel then finds only POST /shorten
// and answers 405. It is the only POST-only path with one or two segments.
Route::get('/{short_url}', [LinkController::class, 'performRedirect'])
    ->where('short_url', '(?!shorten$)[^/]+');
Route::get('/{short_url}/{secret_key}', [LinkController::class, 'performRedirect']);
