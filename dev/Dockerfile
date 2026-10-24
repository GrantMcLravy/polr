# Development/test image for the Laravel 13 port (not the production image).
# PHP_VERSION: 8.3, 8.4 (default) or 8.5.
ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev default-mysql-client \
    && docker-php-ext-install pdo_mysql zip intl bcmath \
    # OPcache is part of PHP itself from 8.5 on.
    && if php -r 'exit(PHP_VERSION_ID < 80500 ? 0 : 1);'; then docker-php-ext-install opcache; fi \
    && rm -rf /var/lib/apt/lists/* \
    && printf 'opcache.enable_cli=1\nopcache.validate_timestamps=1\nopcache.revalidate_freq=0\n' \
        > /usr/local/etc/php/conf.d/zz-dev.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
