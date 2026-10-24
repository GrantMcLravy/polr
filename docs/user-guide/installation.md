# Installation
-----------------

This guide installs Polr 2 on a server with a web server and PHP. To run Polr
with Docker instead, see [Docker](docker.md). To upgrade an existing Polr 2.x
installation, see [Upgrading](upgrading.md).

## Server Requirements

 - A web server: nginx or Apache (other servers work if they can serve `public/`
   and send everything else to `public/index.php`)
 - PHP 8.3, 8.4 or 8.5 (PHP-FPM with nginx, or PHP-FPM / mod_php with Apache)
 - MySQL 5.7 or later, or MariaDB (tested with MySQL 5.7, MySQL 8.4 and
   MariaDB 11.4)
 - [Composer](https://getcomposer.org/)
 - PHP extensions: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`,
   `mbstring`, `openssl`, `pcre`, `pdo`, `pdo_mysql`, `session`, `tokenizer`,
   `xml`, and `phar` and `zlib` for the GeoIP database updates. Most PHP packages
   include these; on Debian/Ubuntu install for example `php8.4-fpm php8.4-mysql
   php8.4-mbstring php8.4-xml php8.4-curl`.

SQLite is not supported by this version.

## Download the source code

```bash
cd /var/www
git clone https://github.com/GrantMcLravy/polr.git
cd polr
# optionally check out a release tag: git checkout <tag>
```

## Install `composer` dependencies

```bash
composer install --no-dev -o
```

Do not delete `composer.lock`: it pins the tested versions of all dependencies.

## Permissions

The web server's PHP user (e.g. `www-data` on Debian/Ubuntu, `apache` or `nginx`
on Fedora/RHEL) needs write access to:

 - `storage/` and `bootstrap/cache/`
 - `.env` during setup, because the setup wizard writes it (see below)

```bash
cp .env.setup .env
chown -R www-data storage bootstrap/cache .env
# With SELinux enforcing (e.g. Fedora/RHEL):
# chcon -R -t httpd_sys_rw_content_t storage bootstrap/cache .env
```

The rest of the files only need to be readable. After setup you may make `.env`
read-only again (`chown root .env && chmod 644 .env`). It contains the database
password, so keep it out of the web root (Polr's web root is `public/`) and away from
other users.

## Running Polr on...

### nginx

Install PHP-FPM (e.g. `apt install php8.4-fpm`). Replace `example.com` and the
PHP-FPM socket path with yours:

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/polr/public;
    index index.php;

    # Never serve dotfiles (.env, .htaccess, .git, ...).
    location ~ /\.(?!well-known/) {
        deny all;
    }

    # Remove trailing slashes, as public/.htaccess does for Apache.
    if (!-d $request_filename) {
        rewrite ^/(.+)/$ /$1 permanent;
    }

    location / {
        try_files $uri /index.php?$query_string;
    }

    location = /index.php {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    }

    location ~ \.php$ {
        return 404;
    }
}
```

Serve Polr over HTTPS on the internet (e.g. with Let's Encrypt), so passwords and
API keys are not sent in the clear.

### Apache

Enable `mod_rewrite` (`a2enmod rewrite`) and add a virtual host. Replace
`example.com` and restart Apache:

```apache
<VirtualHost *:80>
    ServerName example.com
    DocumentRoot "/var/www/polr/public"
    <Directory "/var/www/polr/public">
        Require all granted
        AllowOverride All
    </Directory>
</VirtualHost>
```

`public/.htaccess` sends requests to Polr and removes trailing slashes.

### Other servers and shared hosting

Set the document root to `/PATH_TO_POLR/public`, not the Polr folder itself, and
send requests for files that do not exist to `public/index.php`.

## Create the database

```sql
CREATE DATABASE polr;
CREATE USER 'polr'@'localhost' IDENTIFIED BY 'a long random password';
GRANT ALL PRIVILEGES ON polr.* TO 'polr'@'localhost';
```

## Option 1: Run the setup wizard

Make sure `.env` is a copy of `.env.setup` and writable by PHP (see
[Permissions](#permissions)), then open `/setup` on your Polr site (e.g.
`https://example.com/setup`).

The wizard writes `.env`, creates the database tables and the first administrator,
and, if you enable advanced analytics, downloads the GeoIP database. You can change
the configuration later by editing `.env`.

## Option 2: Configure Polr manually

Write `.env` yourself, using `resources/views/env.blade.php` as the list of settings
(replace each `{!! $escape(...) !!}` with a value). `APP_KEY` must be a random,
32-character string; to generate one:

```bash
php -r 'echo bin2hex(random_bytes(16)), PHP_EOL;'
```

Set `POLR_SETUP_RAN=true`, then create the tables and an administrator:

```bash
php artisan migrate --force
php artisan polr:create-admin admin admin@example.com
# (prompts for the password)
```

Settings can also be given as environment variables (e.g. in a PHP-FPM pool or a
systemd unit); these take precedence over `.env`.

## Behind a reverse proxy

If Polr runs behind a reverse proxy or load balancer, tell it which addresses to
trust so that click records, GeoIP, anonymous API quotas and signup IPs use the
visitor's address from `X-Forwarded-For` instead of the proxy's:

```
TRUSTED_PROXIES=10.0.0.5
# several: TRUSTED_PROXIES=10.0.0.5,192.168.0.0/16
# any (only if Polr cannot be reached except through the proxy): TRUSTED_PROXIES=*
```

By default no proxy is trusted.

## Advanced analytics (GeoIP)

Advanced analytics (`SETTING_ADV_ANALYTICS=true`) records each click's country
using MaxMind's free GeoLite2 City database (`storage/app/geoip.mmdb`). It needs a
MaxMind license key, see [Obtaining a MaxMind License Key](maxmind-license.md):

```
MAXMIND_LICENSE_KEY="your license key"
```

Download the database once (the setup wizard does this when you enable advanced
analytics):

```bash
sudo -u www-data util/update-geoip.sh
```

MaxMind publishes updates twice a week, and its license requires replacing old
copies within 30 days of a new release, so update it regularly with your system's
cron. Edit the web server user's crontab (`sudo crontab -u www-data -e`) and add,
for example, every Wednesday and Saturday at 04:00:

```
0 4 * * 3,6  /var/www/polr/util/update-geoip.sh >> /var/www/polr/storage/logs/geoip-update.log 2>&1
```

The script exits with an error if the update fails (cron can mail you its output)
and leaves the existing database in place. The license key is removed from its
output. If a lookup fails, for example because the database file is missing or
damaged, the click is still recorded and redirected, just without a country.

## Optional: cache the configuration

For a little more speed you can cache the configuration and routes:

```bash
php artisan config:cache
php artisan route:cache
```

When cached, changes to `.env` take effect only after you run these commands again
(or `php artisan optimize:clear` to remove the caches). The setup wizard clears them
itself.
