# Polr 2.3.0 baseline

Recorded behavior of the original Polr 2.3.0 (Lumen 5.1, PHP 7.1, MySQL 5.7),
used to check that the Laravel 13 port behaves the same. See `../MODERNIZATION.md`.

## Contents

| Path | What |
|---|---|
| `fixtures/polr-2.3.0.sql` | A Polr 2.3.0 database created by the real setup wizard and populated over HTTP: 6 users (admin, regular, panel-created admin, signup, inactive, low-quota), links of every kind (counter, custom, secret, disabled, API, bulk, anonymous API, edited, deleted), clicks with GeoIP countries and referers. Timestamps and keys are fixed by `fixtures/normalize.sql`. |
| `fixtures/env/*.env` | `.env` files written by the real setup wizard (`setup-default`, `setup-quirks` with awkward values), the analytics-enabled `baseline.env` used by the scenarios, plus Polr 2.3.0's `.env.setup` and `tests/test_env`. |
| `fixtures/env/*.parsed.json` | What `env()` returned for each key of each file under phpdotenv 1.1. |
| `golden/*.json` | One file per scenario group: every request with its normalized response (status, headers, body or JSON), sent mails, and database state where relevant. |

Known users (password): `polradmin` (AdminPass123, admin), `alice` (AlicePass123),
`bob` (BobPass123, admin), `carol` (CarolPass123), `dave` (inactive), `quota`
(API quota 1/min). API keys: see the constants at the top of `scenarios.py`.

## Regenerating

Requires Docker and Python 3. From Git Bash or a POSIX shell:

```sh
baseline/run.sh          # rebuild everything, then stop the containers
baseline/run.sh --keep   # leave the original app running on http://127.0.0.1:18080
```

`run.sh` exports commit `677080f` (Polr 2.3.0 + docs fixes), builds it, drives the
setup wizard, seeds and dumps the database, probes `.env` parsing and records the
scenarios. Running it twice gives identical `golden/` output; only the APP_KEY and
setup date in `fixtures/env/` change.

## Replaying against the port

```sh
python baseline/scenarios.py --base-url http://127.0.0.1:PORT --out /tmp/port \
    --app-exec "docker compose -f <compose file> exec -T app" \
    --db-exec  "docker compose -f <compose file> exec -T mysql" \
    --mail-url http://127.0.0.1:MAILPORT
diff -r baseline/golden /tmp/port
```

Before each group the script restores `fixtures/polr-2.3.0.sql`, writes
`fixtures/env/baseline.env` plus the group's overrides to the app's `.env`, and
clears the mail catcher.

Normalization masks CSRF tokens, cookie values, the app's own base URL, timestamps
not from the fixture (anything outside 2020), newly generated secret keys, API keys
and recovery keys, and drops the `Date`, `Server`, `X-Powered-By`, `Connection`,
`Keep-Alive`, `Content-Length` and `Transfer-Encoding` headers.

## Recorded behavior worth knowing

- A missing or invalid CSRF token returns **500** with the framework's generic error
  page, not 419. This also applies to `PUT /login`, because CSRF is checked before
  routing.
- Wrong HTTP method on a known path: **405**. `GET /signup` with signups disabled
  falls through to the short-link route and returns **404**.
- `/admin/stats/...` with reversed bounds or an invalid date returns **500** (an existing
  bug: `$stats` is undefined after the caught exception).
- API lookups and analytics calls count against the per-minute API quota, the same as
  shortening.
- `/index.php` and `/index.php/...` return **404**.
- `/{short_url}/` (trailing slash) is a 301 from `public/.htaccess`, so it's web-server
  dependent.
- Admin-created users without API access are stored with `api_key = "0"`. Signups store
  `NULL`.
- phpdotenv 1.1 does **not** expand `${VAR}` inside double quotes, and keeps `#frag` in
  unquoted values.
- Lumen's mail config defaults to `MAIL_ENCRYPTION=tls` (STARTTLS required). The
  scenarios set `MAIL_ENCRYPTION=null` for the mail catcher.
- The database connection uses `timezone '+00:00'` and non-strict SQL mode.
