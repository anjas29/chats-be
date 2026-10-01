#!/bin/sh
# Runs on every container start (web, queue, scheduler and reverb roles alike),
# so environment variables are applied to the cached config.
set -e

cd /var/www/html

php artisan package:discover --ansi

if [ "${AUTORUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

if [ "${APP_ENV:-production}" != "local" ]; then
    php artisan optimize
fi
