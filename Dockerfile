# syntax=docker/dockerfile:1
ARG PHP_VERSION=8.5

# ---------------------------------------------------------------------------
# base: PHP-FPM + Nginx with the extensions the app needs
# ---------------------------------------------------------------------------
FROM serversideup/php:${PHP_VERSION}-fpm-nginx AS base

USER root

RUN install-php-extensions intl gd exif pcntl pdo_mysql redis imagick

COPY docker/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY --chmod=755 docker/entrypoint.d/ /etc/entrypoint.d/

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# composer: production vendor directory
# ---------------------------------------------------------------------------
FROM base AS composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# ---------------------------------------------------------------------------
# assets: Vite build (Filament / app assets)
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json* .npmrc ./
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---------------------------------------------------------------------------
# development: bind-mount the source, add Xdebug
# ---------------------------------------------------------------------------
FROM base AS development

RUN install-php-extensions xdebug

USER www-data

# ---------------------------------------------------------------------------
# production: self-contained image (default target)
# ---------------------------------------------------------------------------
FROM base AS production

COPY --chown=www-data:www-data --from=composer /var/www/html /var/www/html
COPY --chown=www-data:www-data --from=assets /app/public/build /var/www/html/public/build

USER www-data
