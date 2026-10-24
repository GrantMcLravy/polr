# Development/test image for the Laravel 13 port (not the production image).
FROM php:8.4-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev default-mysql-client \
    && docker-php-ext-install pdo_mysql zip intl bcmath opcache \
    && rm -rf /var/lib/apt/lists/* \
    && printf 'opcache.enable_cli=1\nopcache.validate_timestamps=1\nopcache.revalidate_freq=0\n' \
        > /usr/local/etc/php/conf.d/zz-dev.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
