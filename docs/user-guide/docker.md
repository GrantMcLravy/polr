# Docker
-----------------

Polr's `Dockerfile` builds two images:

 - `app` (`--target app`): PHP-FPM with Polr. It runs as the unprivileged
   `www-data` user (uid 82). Only `storage/` and `bootstrap/cache/` are writable.
   `.env` is a symlink to `storage/.env`, so Polr's configuration lives in the
   storage volume.
 - `web` (`--target web`): nginx serving Polr's static files and passing everything
   else to the `app` container (`POLR_FPM_ADDRESS`, default `polr:9000`).

`docker-compose.example.yml` runs both, plus MySQL.

## Prebuilt images

Each push to `master` that passes the tests publishes both images to the GitHub
Container Registry, tagged `latest` and with the short commit hash (e.g. `sha-1f64226`);
`v*` release tags are also published as `1.2.3` and `1.2`:

 - `ghcr.io/grantmclravy/polr-app`
 - `ghcr.io/grantmclravy/polr-web`

To use them instead of building, replace each `build:` section in your
`docker-compose.yml` with the matching `image:`:

```yaml
  polr:
    image: ghcr.io/grantmclravy/polr-app:latest
  web:
    image: ghcr.io/grantmclravy/polr-web:latest
```

Update with `docker compose pull && docker compose up -d`. To stay on a known
version, use a commit or release tag instead of `latest`, and keep `polr` and `web`
on the same tag.

## Getting started

```bash
git clone https://github.com/GrantMcLravy/polr.git
cd polr
cp docker-compose.example.yml docker-compose.yml
```

Create a `.env` file next to `docker-compose.yml` with the database passwords. This
file is read by Docker Compose to fill in the variables in `docker-compose.yml`. It
is not Polr's configuration and is not copied into the images.

```
POLR_DB_PASSWORD=<a long random password>
MYSQL_ROOT_PASSWORD=<another long random password>
# optional:
# POLR_HTTP_PORT=8080
# TRUSTED_PROXIES=172.16.0.0/12
```

```bash
docker compose up -d --build
```

Polr is now on port 8080 (`POLR_HTTP_PORT`). Put it behind your HTTPS reverse proxy
(see [Reverse proxy](#reverse-proxy)).

## Setup

Open `/setup` (e.g. `http://localhost:8080/setup`) and complete the setup wizard.
Database settings for the bundled MySQL service:

 - host `mysql`, port `3306`
 - database `polr`, username `polr`
 - password: your `POLR_DB_PASSWORD`

The wizard writes `storage/.env` in the `polr-storage` volume. It persists across
container restarts, image rebuilds and upgrades.

### Without the wizard

Instead of the wizard, you can configure Polr with environment variables on the
`polr` service (they take precedence over `storage/.env`). Set at least `APP_KEY`
(32 random characters, e.g. from `openssl rand -hex 16`), `POLR_SETUP_RAN=true`, the
`DB_*` and `APP_*` settings (see `resources/views/env.blade.php` for all of them).
Then create the tables and an administrator:

```bash
docker compose exec polr php artisan migrate --force
docker compose exec polr php artisan polr:create-admin admin admin@example.com
```

## Administrator accounts

```bash
docker compose exec polr php artisan polr:create-admin <username> <email>
```

It prompts for the password (`docker compose exec` allocates a terminal).

## GeoIP database updates

With advanced analytics enabled, Polr records the country of each click using
MaxMind's GeoLite2 City database at `storage/app/geoip.mmdb` (in the `polr-storage`
volume). It needs `MAXMIND_LICENSE_KEY` in Polr's configuration, see
[Obtaining a MaxMind License Key](maxmind-license.md).

The containers don't run a scheduler. Update the database by running
`util/update-geoip.sh` in the `polr` container:

```bash
docker compose exec -T polr util/update-geoip.sh
```

MaxMind publishes updates twice a week, and its license requires replacing old
copies within 30 days of a new release, so run this regularly from the **host's**
cron. Open the crontab of a user that can run Docker (`crontab -e`) and add, for
example, every Wednesday and Saturday at 04:00 (adjust the path to the directory
with your `docker-compose.yml`):

```
0 4 * * 3,6  cd /opt/polr && docker compose exec -T polr util/update-geoip.sh >> /var/log/polr-geoip-update.log 2>&1
```

Without Docker Compose, use the container name instead:
`docker exec polr-polr-1 util/update-geoip.sh`.

The script exits with an error if the update fails (cron can mail you its output)
and keeps the existing database. The license key is removed from its output. If a
lookup fails, clicks are still recorded and redirected, without a country.

## Reverse proxy

nginx in the `web` container passes the address it receives connections from to
Polr. If another reverse proxy (Traefik, Caddy, a load balancer...) forwards to the
`web` container, set `TRUSTED_PROXIES` to that proxy's address or network, so that
Polr takes the visitor's address from `X-Forwarded-For`:

```
TRUSTED_PROXIES=172.16.0.0/12
```

Only use `*` if the `web` container cannot be reached except through the proxy.

## Upgrading

```bash
# prebuilt images:
docker compose pull && docker compose up -d
# or, when building from source:
git pull && docker compose up -d --build

docker compose exec polr php artisan migrate --force
```

## Backups

Back up the database (e.g. `docker compose exec mysql mysqldump -uroot -p polr`) and
the `polr-storage` volume, which holds `.env` and the GeoIP database.

## Bind mounts

If you mount a host directory instead of the `polr-storage` volume, it must be
writable by uid 82 (`chown -R 82:82 /path/to/storage`).
