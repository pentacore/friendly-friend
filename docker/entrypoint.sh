#!/bin/sh
set -e

# Cache config, routes, views and events with the runtime environment.
php artisan optimize

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
