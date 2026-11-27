# Deployment (home server, Docker Compose)

One image (`Dockerfile`, FrankenPHP on PHP 8.5) runs every app service with a different command. CI builds it for every release and pushes it to `ghcr.io/pentacore/friendly-friend` ([ci.md](ci.md#releases-releaseyml)); the server pulls it. Nothing site-specific is compiled in, so the same image fits any deploy: everything comes from `.env` at runtime. Stack: `compose.prod.yaml`.

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
# 2) where BROWSERS connect (public; rendered into each page at runtime)
REVERB_BROWSER_HOST=ws.quotes.example.com   # unset: APP_URL's host
REVERB_BROWSER_PORT=443
REVERB_BROWSER_SCHEME=https

# Discord + AI keys: see docs/discord-app.md (DISCORD_REDIRECT_URI must use https://quotes.example.com)
```

Each page carries the browser endpoint in `<meta name="reverb-config">` (from `config/reverb.php` → `browser`, with `REVERB_APP_KEY`), so changing it only needs a restart, not a new image. The old `VITE_REVERB_HOST/PORT/SCHEME` names still work as a fallback.

```bash
docker compose -f compose.prod.yaml pull
docker compose -f compose.prod.yaml up -d
docker compose -f compose.prod.yaml ps          # migrate: Exited (0); web: healthy
curl -fsS http://localhost/up
```

Then log in as an owner (`DISCORD_OWNER_IDS`) and enable your servers on `/admin/guilds`.

## Updating

```bash
git pull                                   # compose file and .env.example changes
docker compose -f compose.prod.yaml pull   # the newest release (:latest)
docker compose -f compose.prod.yaml up -d
```

To pin or roll back to a version, set `IMAGE=ghcr.io/pentacore/friendly-friend:1.2.3` (in the shell, or next to `compose.prod.yaml` in a `.env` that compose reads) before `pull`. To run code that isn't released, build it on the server: `docker build --target app -t friendly-friend:local .`, then `IMAGE=friendly-friend:local docker compose -f compose.prod.yaml up -d`.

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

| Symptom                                                     | Cause                                                                                                                                                                                                     |
| ----------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Site redirects to `http://` or login loops behind the proxy | `TRUSTED_PROXIES` not set (and `SESSION_SECURE_COOKIE=true` with https)                                                                                                                                   |
| Live updates (votes, new quotes) don't arrive               | Browser endpoint: `REVERB_BROWSER_*` wrong (view the page source for `reverb-config`, and check the browser console). Server endpoint: `REVERB_HOST` must be `reverb` inside Docker, not the public host. |
| App services stay "Created"                                 | `migrate` failed: `docker compose -f compose.prod.yaml logs migrate`                                                                                                                                      |
| Bot keeps restarting                                        | `docker compose -f compose.prod.yaml logs bot`: "rejected DISCORD_BOT_TOKEN", or "never finished connecting" (privileged intents off)                                                                     |

## Smoke-testing the stack locally

Run it next to Sail on other ports. Use a throwaway `.env` with `APP_ENV=production`, `DB_HOST=postgres`, `REDIS_HOST=redis`, `WEB_PORT=8088` and `REVERB_PUBLIC_PORT=8089`, build the image (`docker build --target app -t friendly-friend:local .`), then run `IMAGE=friendly-friend:local docker compose -p fftest -f compose.prod.yaml up -d`. To check proxy handling, look at the raw `Location` header of `GET /dashboard` sent with `X-Forwarded-Proto: https`.

## Server-side rendering

Production renders pages in the browser: no SSR server runs, so `compose.prod.yaml` sets `INERTIA_SSR_ENABLED=false` (otherwise every request would first try to reach one). To turn SSR on, build the SSR bundle (`npm run build:ssr`, bundle at `bootstrap/ssr/app.js`), run `php artisan inertia:start-ssr` as an extra service from the same image, and set `INERTIA_SSR_ENABLED=true`. Pages are written to render the same on the server and in the browser (see `.ai/rules/frontend.md`), and CI checks hydration.
