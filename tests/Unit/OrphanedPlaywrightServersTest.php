<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Tests\Support\OrphanedPlaywrightServers;

/**
 * A process whose own parent exits is handed to PID 1, like a Playwright
 * server whose test run died.
 */
function orphanedPlaywrightSpawnOrphan(string $marker): void
{
    new Process(['sh', '-c', sprintf('(exec -a %s sleep 60 &)', escapeshellarg($marker))])->run();
}

function orphanedPlaywrightIsRunning(string $marker): bool
{
    $process = new Process(['pgrep', '-f', $marker]);
    $process->run();

    return trim($process->getOutput()) !== '';
}

it('stops orphaned servers and leaves a live run\'s server alone', function (): void {
    $marker = 'orphan-reap-test-'.bin2hex(random_bytes(4));
    orphanedPlaywrightSpawnOrphan($marker);
    $live = new Process(['bash', '-c', sprintf('exec -a %s sleep 60', escapeshellarg($marker))]);
    $live->start();
    usleep(200_000);

    OrphanedPlaywrightServers::reap($marker);
    usleep(200_000);

    $survivors = new Process(['pgrep', '-f', $marker]);
    $survivors->run();

    expect(array_filter(explode("\n", trim($survivors->getOutput()))))->toBe([(string) $live->getPid()]);

    $live->stop(0);
    expect(orphanedPlaywrightIsRunning($marker))->toBeFalse();
})->skip(PHP_OS_FAMILY !== 'Linux', 'pkill and PID 1 re-parenting are Linux only');
