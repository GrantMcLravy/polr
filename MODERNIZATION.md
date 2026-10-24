# Polr modernization: Lumen 5.1 → Laravel 13 (PHP 8.3–8.5)

Working notes for the `laravel-13` branch. Goal: **keep existing Polr, but on a
supported PHP and framework.** This is a faithful port: existing features, flows,
URLs, API formats, database schema, passwords and `.env` files keep working.
Behavior changes only where security or the platform requires it, and each one is
listed under "Deliberate changes".

## Hard constraints

- **Database:** no changes to `links`, `users`, `clicks` or `migrations`. Keep the six
  migration files and class names; `php artisan migrate --pretend` against a 2.3.0
  database must report nothing to run.
- **URLs:** every route keeps its path, method and name (inventory below).
- **API:** `/api/v2/action/*` and `/api/v2/data/*` keep parameters, JSON and plain-text
  formats, status codes, error messages, `Access-Control-Allow-Origin: *`, key auth,
  anonymous API and quotas.
- **Passwords:** existing bcrypt hashes keep working with `Hash::check`.
- **APP_KEY:** existing 32-char hex keys (no `base64:` prefix) keep working with
  AES-256-CBC. Users having to log in once more is acceptable.
- **Frontend:** Blade views, AngularJS 1.x, jQuery, Bootstrap 3 and `public/` assets
  stay as they are, apart from the edits listed below.
- **Config:** every `env()` call moves into `config/polr.php` (and the framework config
  files) under the same variable names, so existing `.env` files keep working.

## Decisions

| Topic | Decision |
|---|---|
| Web setup wizard | **Kept.** `/setup`, `/setup/finish`, `setup`/`psetup`/`setup_finish` unchanged. |
| GeoIP | **Kept as-is**: `torann/geoip` 1.0 → 3.x, GeoLite2-City, `storage/app/geoip.mmdb`, in-app `geoip:update`, MaxMind license field in setup. Hardened: lookup errors return `null` and never break a redirect. |
| GeoIP updates | A script runs `php artisan geoip:update` on a schedule; the install docs explain how to set it up with system cron. In Docker, the same script runs on a schedule. |
| MaxMind attribution | Added to the about and link stats pages when `SETTING_ADV_ANALYTICS` is on (required by the GeoLite2 license). |
| Trusted proxies | `TRUSTED_PROXIES` (comma-separated CIDRs or `*`); default is none, so behavior is unchanged unless configured. |
| Public API session | **Kept**: the public API still runs the session middleware and sends the session cookie, as today. |
| Duplicate route names | `api_shorten_url`, `api_lookup_url` and `api_link_analytics` each become one `Route::match(['GET','POST'], …)` route, keeping the same names and paths and allowing `route:cache` to work. |
| `polr:create-admin` | **Added** (new, additive) Artisan command to create an admin, mainly for Docker installs. |
| Docker + setup wizard | The image symlinks `.env` → `storage/.env`, so the wizard can write it while only `storage/` and `bootstrap/cache/` are writable. |
| DB connection charset | `utf8` / `utf8_unicode_ci`, matching existing 2.x schemas. |
| Config/route cache | Supported. Setup runs `config:clear` after writing `.env`; the docs say to re-run the caches after editing `.env` (the `/signup` routes are registered based on config). |
| Composer platform | `config.platform.php = 8.3.0`, so one lock file works on 8.3, 8.4 and 8.5. |

## Deliberate changes

1. **Trusted proxies** (opt-in; see above).
2. **MaxMind attribution line** on the about and stats pages.
3. **`polr:create-admin`** command.
4. **GeoIP lookups never throw**: an error records `country = null` instead of failing.
5. **`/api/v2/action/lookup` for a nonexistent link** returns `404 NOT_FOUND` on PHP 8.
   On the old stack it relied on reading an array offset on `false`, which PHP 8 turns
   into a warning and Laravel into a 500.
6. Install and upgrade docs: PHP 8.3+ and the GeoIP update script.

## Behavior that must be pinned (silent breakage otherwise)

- **MySQL session settings exactly as Lumen 5.1 set them:** `sql_mode=''` (use
  `'modes' => []`; Laravel's `strict => false` would set `NO_ENGINE_SUBSTITUTION`) and
  `time_zone='+00:00'` (`'timezone' => '+00:00'`). Link inserts omit the NOT NULL
  columns `creator` and `secret_key`, which have no default. The timezone matters
  because `created_at`/`updated_at` are `TIMESTAMP` columns, which MySQL converts using
  the session time zone.
- **Mail encryption default:** Lumen's mail config defaults `MAIL_ENCRYPTION` to
  `tls` (STARTTLS required), and setup-written `.env` files don't set it. Map this to the
  equivalent Symfony Mailer behavior.
- **phpdotenv 1.1 does not expand `${VAR}`** in double-quoted values; phpdotenv 5
  does. The setup wizard writes passwords in double quotes, so a `${` in a password
  would change meaning. See `baseline/fixtures/env/*.parsed.json`.
- **Native integer fetches, no `PDO::ATTR_STRINGIFY_FETCHES`.** Lumen 5.1 disabled
  emulated prepares, so INT columns already came back as PHP ints (`"clicks": 10`,
  `"api_active": 1`, stats counts) and VARCHAR columns as strings (`"active": "1"`).
  Laravel 13 does the same. (Phase 1 wrongly added `STRINGIFY_FETCHES`; the baseline
  replay caught it and Phase 3 removed it.)
- **Short-link redirects keep `Cache-Control: no-cache`.** Newer Symfony strips
  Cache-Control from 301 responses so browsers cache them indefinitely. Then edits,
  disabling, and click counting would no longer reach repeat visitors. Set it explicitly
  in `LinkController::performRedirect` (phase 4).
- **API lookup `created_at` / `updated_at`** keep the DateTime object form
  `{"date":"…","timezone_type":3,"timezone":"UTC"}` (see `docs/developer-guide/api.md`),
  not Carbon 3's ISO string.
- **Model date serialization** stays `Y-m-d H:i:s` (`serializeDate()`), as shown in the
  admin tables.
- **yajra DataTables 7+** escapes every column by default. HTML columns need
  `rawColumns()`, and only the previously escaped columns get escaped.
- **`url` validation:** Lumen 5.1 used `filter_var(FILTER_VALIDATE_URL)`; Laravel uses a
  regex. Keep the old rule via a custom rule.
- **Bcrypt `verify` off**, so a non-bcrypt hash returns false instead of throwing.
- **Legacy env names:** `CACHE_DRIVER` → `CACHE_STORE`, `QUEUE_DRIVER` →
  `QUEUE_CONNECTION`, `MAIL_DRIVER` → `MAIL_MAILER`. Defaults: file session, file cache,
  sync queue, `DB_CONNECTION=mysql`.
- **phpdotenv 1 → 5:** `.env` files written by the setup wizard must read back to the
  same values. `env.blade.php` uses `{{{ }}}`, which current Blade no longer supports;
  change it to `{{ }}` (same escaping).
- **Fresh-install migrations** (file and class names unchanged; bodies adjusted so a
  fresh install gets exactly the 2.3.0 schema):
  - `timestamps()` is written out as `NOT NULL DEFAULT '0000-00-00 00:00:00'` columns,
    as Laravel 5.1 created them (Laravel 13 makes them nullable).
  - `2017_02_04_025727`: `chunk()` now requires `orderBy('id')`.
  - `2017_02_08_003907`: without DBAL, `->change()` drops `default(0)` on `clicks`, so
    the default is restated.
  - Laravel creates the `migrations` table with an `id` column on fresh installs.
    Existing installs keep theirs without one; both work.
- **Middleware stack as Lumen ran it** (`bootstrap/app.php`):
  - Cookies, session, shared errors and CSRF run as **global** middleware, before
    routing, as in Lumen. So every response, including 404s for unmatched paths, has a
    session and CSRF token, and `PUT /login` without a token is a CSRF 500 as before.
  - Not used, because Lumen didn't have them: `TrimStrings` (would trim passwords,
    breaking logins for passwords with leading or trailing spaces),
    `ConvertEmptyStringsToNull` (changes `""` inputs such as `custom_ending`), and
    `HandleCors` (would answer API `OPTIONS` with 204).
  - `App\Http\Middleware\VerifyCsrfToken` extends Laravel's CSRF middleware. Excluded
    paths skip it entirely, so the public API gets no `XSRF-TOKEN` cookie, as before.
    The token is always required: Laravel 13 also accepts `Sec-Fetch-Site: same-origin`
    without a token, which is turned off.
  - `NoCookiesOnErrorResponses`: in Lumen an exception skipped the rest of the
    middleware, so error responses carried no cookies (the session was still saved).
    Laravel renders exceptions inside the stack, so this outermost middleware removes
    the cookies again. Validation failures are exempt: Lumen turned those into
    responses inside the controller, so they did get cookies.
  - `NoAutomaticOptionsResponses`: Laravel's router answers `OPTIONS` itself with 200
    and an `Allow` header. Lumen returned 405 (404 for unknown paths).
  - Trusted proxies: `config/trustedproxy.php` (`TRUSTED_PROXIES`), read by Laravel's
    `TrustProxies` middleware.
- **Exception rendering** (`App\Exceptions\Renderer`, ported from the Lumen handler):
  404 → redirect to `SETTING_INDEX_REDIRECT` or `errors.404`; other HTTP exceptions →
  `errors.500` / `errors.generic`; `ApiException` → JSON or plain text with CORS header;
  nothing special when `APP_DEBUG` is on. Additions needed under Laravel:
  - a CSRF failure (which Laravel turns into a 419) is a 500;
  - 405 pages have an empty message (Laravel's names the route and allowed methods);
  - AJAX/JSON validation failures return the bare error bag with 422, as Lumen 5.1's
    `validate()` did, not Laravel's `{"message", "errors"}`.
- **No `/up` health route:** it would shadow a short link named `up`.
- **No `/storage/{path}` route:** Laravel 12+ serves the private `local` disk there by
  default (`'serve' => false`), which would shadow secret links with the ending `storage`.
- **`GET /shorten` stays a 405.** Lumen's router let a static path registered for another
  method win over `/{short_url}`. The catch-all excludes `shorten` so Laravel also answers
  405 instead of looking up a short link called `shorten`.
- **Public API middleware is referenced by class.** The Lumen alias `api` can't be reused,
  because Laravel's built-in `api` middleware group takes precedence over an alias of the
  same name.
- **Session cookie** keeps Lumen's name `laravel_session`. Laravel adds
  `SameSite=Lax`, which is accepted (all Polr requests are same-origin).
- **Route order:** register `/{short_url}` and `/{short_url}/{secret_key}` last.
- **PHP 8.4:** `ApiException` (parameter order, implicit nullable, wrong `Exception`
  namespace, dynamic properties), dynamic properties in `StatsHelper`, and
  `$request->user = …` in `ApiMiddleware`.
- **Carbon 3:** `diffIn*()` returns signed floats; audit the stats bound checks.
- **Removed helpers:** `str_random`, `array_get`, `str_limit`.

## Recorded edge cases (see `baseline/README.md`)

The baseline recordings show behavior where Laravel's defaults differ. Unless noted,
the port reproduces the recorded behavior:

- A CSRF failure returns **500**, not Laravel's 419 (decided: keep 500). So does
  `PUT /login`, because CSRF runs before routing.
- Stats page with reversed bounds or an invalid date returns 500 (existing bug, kept).
- Admin-created users without API access are stored with `api_key = "0"`.

## Accepted differences

`dev/compare.py` normalizes the formatting-only ones (`--strict` shows them all):

- **Unexpected-error page body.** For a 500 caused by something other than an HTTP
  exception (CSRF failure, crash), Lumen showed Symfony's generic "Whoops, looks like
  something went wrong." page. The port shows Polr's own `errors/500` page. Same status.
- Set-Cookie attribute case (`httponly`) and `SameSite=Lax`; `Cache-Control: no-cache,
  private` instead of `no-cache`; `charset=utf-8` case; the quoted URL in redirect pages'
  `<meta http-equiv="refresh">`; Blade whitespace around directives.
- New password hashes use bcrypt cost 12 (Laravel's default) instead of 10. Old hashes
  verify unchanged.
- `/index.php` serves the home page (Lumen: 404).
- Web-server level (depends on Apache/nginx, not Polr): `Vary`, `ETag`/`Last-Modified` on
  static files, the trailing-slash 301 from `.htaccess`.
- A non-AJAX validation failure on an AJAX endpoint redirects back to the previous URL
  with its query parameters in their original order (Lumen sorted them). Browsers always
  send AJAX requests with `X-Requested-With`, which gets the JSON response instead.

## Known issues kept as-is (faithful port)

- Secret and recovery keys are compared with `!=` (loose comparison) and secret keys are
  4 hex characters.
- The setup wizard HTML-escapes values it writes to `.env` (e.g. `&` → `&amp;`).

## Route inventory

Web (session + CSRF):

| Method | Path | Name | Action |
|---|---|---|---|
| GET | `/` | index | IndexController@showIndexPage |
| GET | `/logout` | logout | UserController@performLogoutUser |
| GET | `/login` | login | UserController@displayLoginPage |
| POST | `/login` | plogin | UserController@performLogin |
| GET | `/about-polr` | about | StaticPageController@displayAbout |
| GET/POST | `/signup` (only if `POLR_ALLOW_ACCT_CREATION`) | signup / psignup | UserController@displaySignupPage / performSignup |
| GET/POST | `/lost_password` | lost_password / plost_password | UserController@displayLostPasswordPage / performSendPasswordResetCode |
| GET | `/activate/{username}/{recovery_key}` | activate | UserController@performActivation |
| GET/POST | `/reset_password/{username}/{recovery_key}` | reset_password / preset_password | UserController@performPasswordReset |
| GET | `/admin` | admin | AdminController@displayAdminPage |
| POST | `/admin/action/change_password` | change_password | AdminController@changePassword |
| GET | `/admin/stats/{short_url}` | — | StatsController@displayStats |
| GET/POST | `/setup` | setup / psetup | SetupController@displaySetupPage / performSetup |
| GET | `/setup/finish` | setup_finish | SetupController@finishSetup |
| POST | `/shorten` | pshorten | LinkController@performShorten |
| GET | `/{short_url}` | — | LinkController@performRedirect (registered last) |
| GET | `/{short_url}/{secret_key}` | — | LinkController@performRedirect (registered last) |

Internal admin AJAX, prefix `/api/v2` (session + CSRF): POST `link_avail_check`,
`admin/toggle_api_active`, `admin/generate_new_api_key`, `admin/edit_api_quota`,
`admin/toggle_user_active`, `admin/change_user_role`, `admin/add_new_user`,
`admin/delete_user`, `admin/toggle_link`, `admin/delete_link`,
`admin/edit_link_long_url` → AjaxController; GET `admin/get_admin_users`,
`admin/get_admin_links`, `admin/get_user_links` → AdminPaginationController. Existing
`api_*` names kept.

Public API, prefix `/api/v2`, `ApiMiddleware`, no CSRF: GET|POST `action/shorten`
(`api_shorten_url`), POST `action/shorten_bulk` (`api_shorten_url_bulk`), GET|POST
`action/lookup` (`api_lookup_url`), GET|POST `data/link` (`api_link_analytics`).

## Phases (one commit each)

0. **Baseline** (done). `baseline/`: a Docker environment running the original code
   (PHP 7.1, Lumen 5.1, MySQL 5.7, mail catcher), a 2.3.0 database dump created
   through the real setup wizard, `.env` fixtures plus how phpdotenv 1.1 parses them,
   and 26 recorded scenario groups (~260 requests) covering every route above.
1. **Skeleton** (done). Laravel 13 skeleton files (artisan, bootstrap, framework config,
   `public/index.php`, `.htaccess`, storage layout), Composer packages resolved for PHP 8.3+
   (laravel/framework 13.38, yajra 13.3, torann/geoip 3.0.10, geoip2 3.4,
   google/recaptcha 1.3), framework config pinned as above, fresh-install migrations
   fixed. `dev/` has a PHP 8.4 + MySQL 5.7 development environment.
2. **Routing** (done). `routes/web.php` per the inventory above, checked by
   `dev/check_routes.py` against `route:list` (cached and uncached).
3. **Bootstrap, middleware, exceptions** (done). Middleware stack and exception rendering
   as above. The app now serves requests. `dev/replay.sh` replays the baseline against the
   dev environment and `dev/compare.py` diffs the results: 207/264 steps identical,
   with the rest belonging to phases 4–5 or listed as accepted differences.
4. **Code fixes** (done).
   - All `env()` calls in app code, views and routes now read `config('polr.*')`
     (`config/polr.php`, same variable names) or `mail.from.*`, so `config:cache` and
     `route:cache` work.
   - Removed helpers replaced (`Str::random`, `Arr::get`, `Str::limit`).
   - DataTables: new facade namespace, first-class callables for cell renderers (yajra 13
     reflects on closures), and yajra's extra `disableOrdering` key removed.
   - Model dates serialize as `Y-m-d H:i:s`; API lookup dates in the DateTime object form.
   - Lumen's `url` rule (`FILTER_VALIDATE_URL`) restored via `App\Validation\Validator`.
   - Short-link 301s send `Cache-Control: no-cache`.
   - PHP 8.4: `ApiException`, `StatsHelper` properties, the API user moved to a request
     attribute, null passed to string functions. Carbon 3 day differences truncated to
     whole days as before.
   - API lookup of an unknown link returns 404 again.
   - Pulled forward from phase 5: `config/geoip.php` sets `cache_tags` to `null`.
     torann/geoip 3 otherwise uses cache tags, which the file cache can't do, so every
     redirect failed.

   Replay: 250/264 steps identical, no PHP deprecations logged. The remaining 14 are
   all listed under "Accepted differences".
5. **GeoIP, setup wizard, admin command.** torann v3, hardened lookups, the
   `geoip:update` script, setup wizard port, `polr:create-admin`.
6. **Tests.** PHPUnit on MySQL: feature tests (per spec §5 Phase 6), replay of the
   baseline responses, `migrate --pretend` on the 2.3.0 dump, old bcrypt hash and old
   APP_KEY checks; GitHub Actions matrix on 8.3/8.4/8.5.
7. **Packaging and docs.** Dockerfile (php-fpm, non-root, `.env` symlink),
   `docker-compose.example.yml` (polr, nginx, optional mysql, scheduled GeoIP update),
   nginx config, and install/upgrade docs (cron for `geoip:update`, never edit `.env`
   without clearing the caches).

## Exit checklist

- `composer audit` clean.
- Tests pass on PHP 8.3, 8.4 and 8.5.
- `migrate --pretend` against the 2.3.0 dump: nothing to run.
- On a copy of a 2.3.0 database: an existing admin logs in, existing links redirect, the
  stats page renders, the admin tables load, and an existing API key works.
- `route:list` matches the inventory above.
- `grep -rn "env(" app/ resources/` returns nothing.
- No references to Lumen remain.
- `config:cache` and `route:cache` succeed, and the app works with them.
