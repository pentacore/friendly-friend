# Deployment (home server, Docker Compose)

One image (`Dockerfile`, FrankenPHP on PHP 8.5) runs every app service with a different command. Stack: `compose.prod.yaml`.

| Service     | Command                       | Notes                                                                                                                                                                                                             |
| ----------- | ----------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `migrate`   | `php artisan migrate --force` | One-shot. Every app service below waits for it to **exit successfully**, so jobs never hit missing tables on a first deploy or after a schema change.                                                             |
| `web`       | `frankenphp php-server`       | Port `${WEB_PORT:-80}`. Healthcheck: `curl /up`.                                                                                                                                                                  |
| `horizon`   | `php artisan horizon`         | Queue workers. Autoscales; "Worker STOPPED Interrupted" lines while it scales down idle workers are normal.                                                                                                       |
| `scheduler` | `php artisan schedule:work`   | Member sync every 3 hours, etc.                                                                                                                                                                                   |
| `reverb`    | `php artisan reverb:start`    | WebSockets. Port `${REVERB_PUBLIC_PORT:-8080}`.                                                                                                                                                                   |
| `bot`       | `php artisan bot:run`         | Discord gateway. Refuses to start with a rejected token and exits on a dead connection (watchdog); Docker restarts it with a growing delay. `docker compose -f compose.prod.yaml exec bot php artisan bot:status` |
| `postgres`  | Postgres 18                   | Volume `postgres`                                                                                                                                                                                                 |
| `redis`     | Redis                         | Volume `redis`                                                                                                                                                                                                    |

- Every app container runs `php artisan optimize` on start (`docker/entrypoint.sh`).
- `LOG_CHANNEL=stderr` is set for all app containers, so **`docker compose logs` shows the Laravel logs**. Nothing is written to `storage/logs` in production.
- Uploaded files live in the `storage` volume (`storage/app`).

## Prerequisites

- Docker Engine with the Compose plugin.
- A TLS reverse proxy in front (Caddy, Traefik, nginx, Nginx Proxy Manager…) that forwards:
    - `https://quotes.example.com` → `web:80` (or host port `WEB_PORT`)
    - the WebSocket host (e.g. `wss://ws.quotes.example.com`, or a `/app` path on the same host) → `reverb:8080` (or host port `REVERB_PUBLIC_PORT`), with WebSocket upgrade headers.

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
APP_KEY=base64:...                 # generate: echo "base64:$(head -c32 /dev/urandom | base64)"
TRUSTED_PROXIES=*                  # or your proxy's IP; needed for https URLs and secure cookies
SESSION_SECURE_COOKIE=true

DB_HOST=postgres
DB_PASSWORD=<strong password>
REDIS_HOST=redis

# Reverb: two different endpoints
# 1) where the APP publishes events (server to server, inside Docker)
REVERB_APP_KEY=<random>
REVERB_APP_SECRET=<random>
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http
# 2) where BROWSERS connect (public; compiled into the frontend at build time)
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=ws.quotes.example.com
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https

# Discord + AI keys: see docs/discord-app.md (DISCORD_REDIRECT_URI must use https://quotes.example.com)
```

`compose.prod.yaml` passes `APP_NAME`, `REVERB_APP_KEY` and the `VITE_REVERB_*` values to the image build. The build stops with an error if `REVERB_APP_KEY` or `VITE_REVERB_HOST` is missing. **Rebuild after changing them** (`--build`), because they're compiled into the JavaScript.

```bash
docker compose -f compose.prod.yaml up -d --build
docker compose -f compose.prod.yaml ps          # migrate: Exited (0); web: healthy
curl -fsS http://localhost/up
```

Then log in as an owner (`DISCORD_OWNER_IDS`) and enable your servers on `/admin/guilds`.

## Updating

```bash
git pull
docker compose -f compose.prod.yaml up -d --build
```

`migrate` runs again first. The other services are recreated on the new image once it succeeds. If a migration fails, the app services keep running the old containers. Check `docker compose -f compose.prod.yaml logs migrate`.

## Logs

```bash
docker compose -f compose.prod.yaml logs -f web horizon bot
```

## Backups

Nightly database dump (host crontab):

```cron
0 3 * * * cd /srv/friendly-friend && docker compose -f compose.prod.yaml exec -T postgres pg_dump -U sail friendly_friend | gzip > backups/db-$(date +\%F).sql.gz
```

Restore: `gunzip -c backups/db-YYYY-MM-DD.sql.gz | docker compose -f compose.prod.yaml exec -T postgres psql -U sail friendly_friend`.

## Troubleshooting

| Symptom                                                     | Cause                                                                                                                                                                                                    |
| ----------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Site redirects to `http://` or login loops behind the proxy | `TRUSTED_PROXIES` not set (and `SESSION_SECURE_COOKIE=true` with https)                                                                                                                                  |
| Live updates (votes, new quotes) don't arrive               | Browser endpoint: `VITE_REVERB_*` wrong, or the image wasn't rebuilt after changing them. Check the browser console. Server endpoint: `REVERB_HOST` must be `reverb` inside Docker, not the public host. |
| App services stay "Created"                                 | `migrate` failed: `docker compose -f compose.prod.yaml logs migrate`                                                                                                                                     |
| Bot keeps restarting                                        | `docker compose -f compose.prod.yaml logs bot`: "rejected DISCORD_BOT_TOKEN", or "never finished connecting" (privileged intents off)                                                                    |

## Smoke-testing the stack locally

Run it next to Sail on other ports. Use a throwaway `.env` with `APP_ENV=production`, `DB_HOST=postgres`, `REDIS_HOST=redis`, `VITE_REVERB_HOST=<anything>`, `WEB_PORT=8088` and `REVERB_PUBLIC_PORT=8089`, then run `docker compose -p fftest -f compose.prod.yaml up -d --build`. To check proxy handling, look at the raw `Location` header of `GET /dashboard` sent with `X-Forwarded-Proto: https`.
