# Friendly Friend

A private website and Discord bot for a group of friends across one or more Discord servers.

- **Quotes**: save what people said straight from Discord (reply with a mention, the "Save quote" app command, `/quote add`, or a channel the bot watches). AI tidies each one into lines with speakers. On the site you can browse, search, vote, play "this or that" and see the top lists.
- **Events**: plan get-togethers with RSVPs, a date poll, questions, food (bring list and shopping list), rides, and shared costs with settle-up. The bot posts announcements, mirrors the event as a Discord event and sends reminders.
- **Stats**: server activity covering messages, reactions, voice time, games and time spent together. Members can opt out, and opting out never hides quotes.

Login is Discord only. Who gets in is decided per server by its roles, and owners manage servers and settings on the site.

## Stack

Laravel 13 on PHP 8.5, PostgreSQL, Redis, Horizon and Reverb, with an Inertia 3, Vue 3 and TypeScript frontend (Vite+, Tailwind 4, shadcn-vue). The bot runs on DiscordPHP; AI goes through laravel/ai (OpenAI, Anthropic or Gemini with failover, plus Jev through OpenRouter for cheap classification). Typed routes come from Wayfinder and typed data from [Laravel Typefinder](https://github.com/pentacore/Laravel-Typefinder).

## Getting started

Everything runs in [Laravel Sail](https://laravel.com/docs/sail) (Docker), so you only need Docker on the host.

```bash
git clone git@github.com:pentacore/friendly-friend.git && cd friendly-friend
cp .env.example .env
docker run --rm -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/c -v "$PWD:/app" -w /app composer:2 composer install
vendor/bin/sail up -d
vendor/bin/sail artisan key:generate
vendor/bin/sail artisan migrate
vendor/bin/sail npm install
vendor/bin/sail npm run dev
```

Then open http://localhost. To log in and run the bot you need a Discord application, which [docs/discord-app.md](docs/discord-app.md) walks through. See [docs/setup.md](docs/setup.md) for the other local URLs (Horizon, Telescope, Mailpit) and the bot service.

## Checks

These are the same checks CI runs. Run them before every commit:

```bash
vendor/bin/sail artisan typefinder:generate --check --strict
vendor/bin/sail composer test        # Rector dry run, Pint, Larastan at max level, Pest
vendor/bin/sail npm run check        # lint and format (Vite+)
vendor/bin/sail npm run types:check  # vue-tsc
```

If you changed pages, also run the browser suite against a fresh build, then look at the screenshots in `tests/Browser/Screenshots`:

```bash
vendor/bin/sail npm run build
vendor/bin/sail composer test:browser
```

## Documentation

| Topic                                        | Where                                                                                              |
| -------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| Local setup, URLs, generated code            | [docs/setup.md](docs/setup.md)                                                                     |
| How the parts fit together, scheduled jobs   | [docs/architecture.md](docs/architecture.md)                                                       |
| Login and access                             | [docs/auth.md](docs/auth.md)                                                                       |
| Discord application (owner tasks)            | [docs/discord-app.md](docs/discord-app.md)                                                         |
| The bot process                              | [docs/bot.md](docs/bot.md)                                                                         |
| Quotes, events, stats                        | [docs/quotes.md](docs/quotes.md), [docs/events.md](docs/events.md), [docs/stats.md](docs/stats.md) |
| Admin: servers and owner settings            | [docs/admin.md](docs/admin.md)                                                                     |
| CI                                           | [docs/ci.md](docs/ci.md)                                                                           |
| Deployment (Docker Compose on a home server) | [docs/deployment.md](docs/deployment.md)                                                           |
| Domain words and their code names            | [GLOSSARY.md](GLOSSARY.md)                                                                         |
| What's open and what's done                  | [ROADMAP.md](ROADMAP.md), [COMPLETED.md](COMPLETED.md)                                             |

## Conventions

Conventions, design decisions and known traps live in [`.ai/rules/`](.ai/rules). Start with `general.md`; `index.md` maps paths to the rule files that cover them. They change in the same commit as the code, along with the docs and the ROADMAP. Process rules for AI agents come from `.ai/guidelines/workflow.md`, which Laravel Boost combines into `AGENTS.md`. Multi-step procedures (formatting, browser tests, working in a worktree) are skills in [`.ai/skills/`](.ai/skills).
