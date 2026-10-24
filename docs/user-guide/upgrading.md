# Upgrading Polr
-----------------
Back up your database and files before upgrading.

## Upgrading from Polr 2.3.0 and earlier 2.x releases

This version runs Polr 2 on Laravel 13 and PHP 8.3 or later. Earlier 2.x releases
were built on Lumen 5.1 and only ran on PHP 5.6–7.x, which no longer receives
security fixes. Your database, links, users, API keys, `.env` file and
`storage/app/geoip.mmdb` are used as they are; no data migration is needed.

1. Back up the database, `.env` and `storage/`.
2. Install PHP 8.3, 8.4 or 8.5 with the extensions listed in the
   [installation guide](installation.md#server-requirements), and point your web
   server at the new PHP version (e.g. the new PHP-FPM socket in nginx). If you use
   nginx, also compare your configuration with the one in the installation guide.
3. Replace the code, keeping your `.env` and `storage/`:

    ```bash
    cd /var/www/polr
    git remote set-url origin https://github.com/GrantMcLravy/polr.git
    git pull
    composer install --no-dev -o
    ```

4. Make `bootstrap/cache/` writable by the web server
   (`chown -R www-data bootstrap/cache`).
5. Check that the database needs no changes. This should print "Nothing to migrate":

    ```bash
    php artisan migrate --pretend --force
    ```

6. Set up the GeoIP update cron job if you use advanced analytics (see
   [Advanced analytics](installation.md#advanced-analytics-geoip)). Earlier releases
   only downloaded the database during setup.

After upgrading, everyone has to log in once more, because the session format
changed.

### What else changes

 - Optional: you can now trust a reverse proxy for visitors' addresses with
   `TRUSTED_PROXIES` (see [Behind a reverse proxy](installation.md#behind-a-reverse-proxy)).
   By default nothing changes.
 - The about and link statistics pages show MaxMind's attribution when advanced
   analytics is enabled, as the GeoLite2 license requires.
 - If a GeoIP lookup fails (e.g. a damaged database file), the click is recorded
   without a country instead of the redirect failing.
 - `php artisan polr:create-admin` creates administrator accounts from the command
   line.
 - `php artisan config:cache` and `route:cache` are supported. If you use them, run
   them again after editing `.env`.
 - Unexpected errors show Polr's own error page.

### Moving to Docker

To move an existing installation to the [Docker images](docker.md), copy your `.env`
into the storage volume as `.env` and your `storage/app/geoip.mmdb` to `app/` in the
volume, and point the `DB_*` settings at your database.

## Upgrading from 1.x

There are breaking changes between 2.x and 1.x; it is not possible to automatically
upgrade to 2.x.
