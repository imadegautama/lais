# syntax=docker/dockerfile:1

# PHP is pinned to a patch release on purpose. 8.4.27 fixes CVE-2026-109618
# and CVE-2026-109619, two RCEs exploited on Apache mod_php, the SAPI this
# image serves with. Raise the tag deliberately when a newer security
# release ships; never use a floating 8.4 tag.
ARG PHP_VERSION=8.4.27

# Production PHP dependencies. Platform extensions are checked in the final stage.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist \
    --no-interaction --no-progress --ignore-platform-reqs

# Vite assets. The Wayfinder plugin runs `php artisan wayfinder:generate`
# during the build, so this stage needs PHP and Node together.
FROM php:${PHP_VERSION}-cli AS assets
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci
COPY --from=vendor /app/vendor ./vendor
COPY . .
ARG VITE_APP_NAME=Lais
ENV VITE_APP_NAME=${VITE_APP_NAME}
RUN npm run build

FROM php:${PHP_VERSION}-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libonig-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libwebp-dev \
        libfreetype6-dev \
        unzip \
        supervisor \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install pdo_mysql intl mbstring bcmath zip pcntl opcache gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www

ENV APACHE_DOCUMENT_ROOT=/var/www/public
# ServerTokens Prod: the Server header names Apache without its version.
RUN a2enmod rewrite \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri -e 's/^ServerTokens .*/ServerTokens Prod/' -e 's/^ServerSignature .*/ServerSignature Off/' /etc/apache2/conf-available/security.conf
COPY ./docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY ./docker/php/php.ini "$PHP_INI_DIR/conf.d/zz-app.ini"

COPY ./docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY ./docker/supervisor/supervisord.d/ /etc/supervisor/supervisord.d/

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build

# dump-autoload also runs package:discover.
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-interaction \
    && rm /usr/local/bin/composer \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY --chmod=755 ./docker/entrypoint.sh /usr/local/bin/entrypoint.sh

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up > /dev/null || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
