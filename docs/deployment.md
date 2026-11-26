# Deployment (home server, Docker Compose)

One image (`Dockerfile`, FrankenPHP on PHP 8.5) runs every app service with a different command. Stack: `compose.prod.yaml`.

| Service     | Command                     | Notes                                                                                                      |
| ----------- | --------------------------- | ---------------------------------------------------------------------------------------------------------- |
| `web`       | `frankenphp php-server`     | Runs `migrate --force` on start (`RUN_MIGRATIONS=true`). Port `${WEB_PORT:-80}`                            |
| `horizon`   | `php artisan horizon`       | Queue workers. Autoscales; "Worker STOPPED Interrupted" lines while it scales down idle workers are normal |
| `scheduler` | `php artisan schedule:work` |                                                                                                            |
| `reverb`    | `php artisan reverb:start`  | WebSockets. Port `${REVERB_PUBLIC_PORT:-8080}`                                                             |
| `bot`       | `php artisan bot:run`       | Added in Phase 3                                                                                           |
| `postgres`  | Postgres 18                 | Volume `postgres`                                                                                          |
| `redis`     | Redis                       | Volume `redis`                                                                                             |

Every app container runs `php artisan optimize` on start (`docker/entrypoint.sh`).

## Prerequisites

- Docker Engine with the Compose plugin
- A reverse proxy with TLS in front of `web` and `reverb` (Caddy, Traefik, nginx…), or expose FrankenPHP directly and set `SERVER_NAME` to your domain for automatic HTTPS.

## First deploy

```bash
git clone git@github.com:pentacore/friendly-friend.git && cd friendly-friend
cp .env.example .env
```

Edit `.env` for production:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://quotes.example.com
APP_KEY=            # generate: docker run --rm friendly-friend:latest php artisan key:generate --show
DB_HOST=postgres
DB_PASSWORD=<strong password>
REDIS_HOST=redis
REVERB_APP_KEY=<random>
REVERB_APP_SECRET=<random>
REVERB_HOST=quotes.example.com   # public host the browser connects to
REVERB_PORT=443
REVERB_SCHEME=https
# Discord + AI keys: see docs/discord-app.md
```

Note that `VITE_REVERB_*` values are compiled into the frontend at **image build time**, so rebuild after changing them.

```bash
docker compose -f compose.prod.yaml up -d --build
docker compose -f compose.prod.yaml ps
curl -fsS http://localhost/up
```

## Updating

```bash
git pull
docker compose -f compose.prod.yaml up -d --build
```

`web` runs migrations on start. Horizon picks up new code when its container is recreated.

## Logs

```bash
docker compose -f compose.prod.yaml logs -f web horizon
docker compose -f compose.prod.yaml exec web tail -f storage/logs/laravel.log
```

## Backups

Nightly database dump (host crontab):

```cron
0 3 * * * cd /srv/friendly-friend && docker compose -f compose.prod.yaml exec -T postgres pg_dump -U sail friendly_friend | gzip > backups/db-$(date +\%F).sql.gz
```

Restore: `gunzip -c backups/db-YYYY-MM-DD.sql.gz | docker compose -f compose.prod.yaml exec -T postgres psql -U sail friendly_friend`.

## Smoke-testing the stack locally

Run it next to Sail on other ports by setting `WEB_PORT=8088` and `REVERB_PUBLIC_PORT=8089` in a throwaway `.env` with `APP_ENV=production`, `DB_HOST=postgres` and `REDIS_HOST=redis`, then `docker compose -p fftest -f compose.prod.yaml up -d --build`.
