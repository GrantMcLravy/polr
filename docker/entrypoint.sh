#!/bin/sh
# Prepare the storage volume, then run the given command (php-fpm by default).
set -e

cd /var/www/html

# A new (or bind-mounted) storage volume may be empty.
mkdir -p storage/app storage/logs storage/framework/cache/data \
    storage/framework/sessions storage/framework/views

# .env is a symlink to storage/.env. Until Polr is configured, start from the
# setup template so the web setup wizard (/setup) can run and write it.
if [ ! -e storage/.env ]; then
    cp .env.setup storage/.env
fi
# It holds the database password and APP_KEY.
chmod 600 storage/.env

exec "$@"
