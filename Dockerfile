# Polr container images. See docs/user-guide/docker.md.
#
#   docker build --target app -t polr-app .   # PHP-FPM with the application
#   docker build --target web -t polr-web .   # nginx with Polr's public/ assets
#
# The app image runs as the unprivileged www-data user; only storage/ and
# bootstrap/cache/ are writable. .env is a symlink to storage/.env, so the
# setup wizard can write it and it persists in the storage volume.

ARG PHP_VERSION=8.4

# --- Composer dependencies -------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress \
        --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize

# --- Application (PHP-FPM) -------------------------------------------------
FROM php:${PHP_VERSION}-fpm-alpine AS app

RUN docker-php-ext-install pdo_mysql \
    # OPcache is part of PHP itself from 8.5 on.
    && if php -r 'exit(PHP_VERSION_ID < 80500 ? 0 : 1);'; then docker-php-ext-install opcache; fi \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    # The FPM master runs unprivileged, so the pool cannot switch users.
    && sed -i -e 's/^user = /;user = /' -e 's/^group = /;group = /' /usr/local/etc/php-fpm.d/www.conf
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-polr.ini"

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/polr-entrypoint

RUN php artisan package:discover --ansi \
    # The base image leaves /var/www/html writable for www-data.
    && chown root:root /var/www/html && chmod 755 /var/www/html \
    && mkdir -p storage/app storage/logs storage/framework/cache/data storage/framework/sessions \
        storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && ln -s storage/.env .env \
    && chmod 755 /usr/local/bin/polr-entrypoint util/update-geoip.sh

USER www-data
VOLUME /var/www/html/storage
ENTRYPOINT ["polr-entrypoint"]
CMD ["php-fpm"]

# --- Web server (nginx) ----------------------------------------------------
FROM nginx:1.28-alpine AS web
# Address of the app container's PHP-FPM (envsubst'd into the template).
ENV POLR_FPM_ADDRESS=polr:9000
COPY docker/nginx.conf.template /etc/nginx/templates/default.conf.template
COPY --from=app /var/www/html/public /var/www/html/public
