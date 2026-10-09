# Dev tooling image: PHP CLI with the extensions OpenMage 20.x requires, plus Composer.
ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-cli

COPY --from=mlocati/php-extension-installer:2.7 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions ftp gd intl mysqli pdo_mysql soap zip \
    && apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.9 /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_HOME=/tmp/composer \
    COMPOSER_CACHE_DIR=/tmp/composer-cache
WORKDIR /app
