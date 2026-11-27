# syntax=docker/dockerfile:1

# ---- Base: FrankenPHP on PHP 8.5 with the extensions the app needs ----
FROM dunglas/frankenphp:1-php8.5 AS base
RUN install-php-extensions pdo_pgsql pgsql redis pcntl intl zip bcmath opcache
WORKDIR /app

# ---- Composer dependencies (no dev) ----
FROM base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ---- Frontend build. Needs PHP: Wayfinder generates routes at build time. ----
# Typefinder output is committed, so its build step is skipped (TYPEFINDER_SKIP_BUILD).
# VITE_* values are compiled into the JavaScript: they must be the PUBLIC
# app name and WebSocket endpoint browsers connect to (passed as build args
# by compose.prod.yaml from .env). Real environment variables take precedence
# over the .env copied below.
FROM vendor AS assets
ARG VITE_APP_NAME="Friendly Friend"
ARG VITE_REVERB_APP_KEY=""
ARG VITE_REVERB_HOST=""
ARG VITE_REVERB_PORT="443"
ARG VITE_REVERB_SCHEME="https"
ENV VITE_APP_NAME=${VITE_APP_NAME} \
    VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY} \
    VITE_REVERB_HOST=${VITE_REVERB_HOST} \
    VITE_REVERB_PORT=${VITE_REVERB_PORT} \
    VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME}
COPY --from=node:24-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm
RUN cp .env.example .env && php artisan key:generate --force
RUN npm ci --no-audit --no-fund && TYPEFINDER_SKIP_BUILD=1 npm run build

# ---- Runtime image ----
FROM base AS app
ENV APP_ENV=production \
    APP_DEBUG=false \
    SERVER_NAME=:80
COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chown -R www-data:www-data storage bootstrap/cache \
    && mkdir -p /data/caddy /config/caddy \
    && chown -R www-data:www-data /data /config
USER www-data
EXPOSE 80
ENTRYPOINT ["entrypoint"]
CMD ["frankenphp", "php-server", "--root", "/app/public", "--listen", ":80"]
