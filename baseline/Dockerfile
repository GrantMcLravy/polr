# Runs the original Polr 2.3.0 (Lumen 5.1) code so its behavior can be
# recorded. Not for production use.

# Install vendor/ with a modern Composer image (has git/unzip); the locked
# packages are installed as-is and then copied into the PHP 7.1 runtime.
FROM composer:2.2 AS vendor
WORKDIR /app
COPY src/composer.json src/composer.lock ./
# (autoloader is generated after the full copy below)
RUN composer install --no-dev --no-interaction --prefer-dist \
        --ignore-platform-reqs --no-scripts --no-autoloader
COPY src/ ./
RUN composer dump-autoload --no-dev --optimize --ignore-platform-reqs

FROM php:7.1-apache
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite remoteip \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Let the capture scripts choose the client IP (for GeoIP and anonymous API
# quotas) by trusting X-Forwarded-For from the Docker network only.
RUN printf 'RemoteIPHeader X-Forwarded-For\nRemoteIPInternalProxy 172.16.0.0/12\nRemoteIPInternalProxy 192.168.0.0/16\nRemoteIPInternalProxy 10.0.0.0/8\n' \
        > /etc/apache2/conf-enabled/remoteip.conf

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
RUN cp .env.setup .env \
    && chown -R www-data:www-data storage .env
