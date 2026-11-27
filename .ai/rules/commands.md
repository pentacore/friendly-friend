---
paths:
    - 'app/Console/Commands/**'
    - 'routes/console.php'
---

# Commands

## Artisan command names

Name commands `area:verb` or `area:verb-noun` (`bot:run`, `discord:sync-members`, `quotes:import`), where the area is the part of the app it belongs to. Keep the body thin: call an action or dispatch a job (`dispatch_sync()` when the command should wait), and say what happened with `info()` / `warn()`. Return an exit code from commands that can fail.
