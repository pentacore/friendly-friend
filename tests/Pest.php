<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;
use Tests\Support\OrphanedPlaywrightServers;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

// Playwright servers left behind by killed browser runs pile up in the Sail
// container. Only orphans (children of PID 1) are stopped, never a live run's
// server. REAP_ORPHANED_PLAYWRIGHT=0 leaves them alone.
if (getenv('REAP_ORPHANED_PLAYWRIGHT') !== '0') {
    OrphanedPlaywrightServers::reap();
}

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser');

// No test may reach a real service (Discord, AI providers): fake it with Http::fake() or a Fake* client.
pest()->beforeEach(function (): void {
    Http::preventStrayRequests();
})->in('Feature');

// Browser tests render the built assets (npm run build), never a running
// dev server, so they behave the same locally and in CI.
pest()->beforeEach(function (): void {
    Vite::useHotFile(storage_path('framework/testing/no-vite-hot-file'));
    // The app under test runs in this process: it must not reach Discord or AI providers either.
    Http::preventStrayRequests();
    config()->set('discord.bot_token', '');
})->in('Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something(): void
{
    // ..
}
