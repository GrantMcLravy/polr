# Development/test image for the Laravel 13 port (not the production image).
FROM php:8.4-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev default-mysql-client \
    && docker-php-ext-install pdo_mysql zip intl bcmath \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
