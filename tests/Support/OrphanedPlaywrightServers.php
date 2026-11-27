<?php

declare(strict_types=1);

namespace Tests\Support;

use Symfony\Component\Process\Process;

/**
 * Stops Playwright servers whose test run is gone.
 *
 * pest-plugin-browser starts `playwright run-server` as a child of the PHP
 * test process and only stops it when Pest ends normally. A run that is
 * killed (timeout, interrupted command, OOM) never gets there, so its server
 * is handed to PID 1 and runs on. A live run's server is always a child of
 * that run's PHP process, so stopping only PID 1's children never touches
 * another session's run.
 */
final class OrphanedPlaywrightServers
{
    public const string PATTERN = 'playwright run-server';

    /**
     * Stop every orphaned process whose command line matches the pattern. A
     * no-op off Linux.
     */
    public static function reap(string $pattern = self::PATTERN): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return;
        }

        new Process(['pkill', '-KILL', '-P', '1', '-f', $pattern])->run();
    }
}
