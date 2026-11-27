---
name: running-browser-tests
description: 'Use when running Pest browser tests (tests/Browser) locally: the whole suite, one file, a filtered run, or the SSR hydration check.'
---

# Running browser tests

The browser suite (`tests/Browser`) is separate from `composer test`. It renders the **built** assets, never a running dev server (`tests/Pest.php` points Vite at a hot file that doesn't exist), so build first.

1. Build: `vendor/bin/sail npm run build`. For the hydration check, build the SSR bundle too: `vendor/bin/sail npm run build:ssr`.
2. For the hydration check, start the SSR server in the background: `vendor/bin/sail artisan inertia:start-ssr`. Without it `HydrationBrowserTest` skips (CI always runs it). Stop it afterwards with `vendor/bin/sail artisan inertia:stop-ssr`.
3. Run, serially (never `--parallel`):
    - Whole suite: `vendor/bin/sail composer test:browser`
    - One file: `vendor/bin/sail artisan test --testsuite=Browser tests/Browser/EventsBrowserTest.php`
    - Filtered: `vendor/bin/sail artisan test --testsuite=Browser --filter=dark`
    - The layout gate for one page: `vendor/bin/sail artisan test --testsuite=Browser tests/Browser/LayoutGateTest.php --filter=events.show`
    - One CI shard: `vendor/bin/sail artisan test --testsuite=Browser --shard=2/3`
    - Whole suite with a timing refresh, after adding or renaming a browser test class: `vendor/bin/sail artisan test --testsuite=Browser --update-shards`. Commit `tests/.pest/shards.json` only after a fully green run.
4. Look at the screenshots in `tests/Browser/Screenshots` (git-ignored), in light and dark mode and at phone width (the layout gate saves `gate-<route>-<screen>-<scheme>.png` for every page). A green run doesn't prove the page looks right.

When a CI shard fails, its screenshots are an artifact: `gh run download <run-id> -n browser-screenshots-<shard>`.

A test that fails on a timeout or a stray request usually means a page calls something the test didn't fake: fake it with `Http::fake()` or a `Fake*` client. Browser tests block stray HTTP and blank the bot token, just like feature tests.
