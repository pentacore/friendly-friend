#!/bin/sh
set -e

# Cache config, routes, views and events with the runtime environment.
php artisan optimize

exec "$@"
