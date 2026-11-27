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
# Nothing site-specific is compiled in: browsers read where Reverb is from the
# page at runtime (<meta name="reverb-config">), so one image fits every deploy.
# Only the app name used in page titles is baked in.
FROM vendor AS assets
ARG VITE_APP_NAME="Friendly Friend"
ENV VITE_APP_NAME=${VITE_APP_NAME}
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
