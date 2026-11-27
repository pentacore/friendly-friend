<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Timeout;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Redis hands a reserved job to another worker once `retry_after` seconds
 * pass, even while the first worker is still running it. Every queued job
 * must therefore stop before `retry_after`: with its own `#[Timeout]`, or
 * with the Horizon supervisor's `timeout` when it declares none.
 *
 * @return array<class-string, int|null> each queued job and the timeout it declares
 */
function queueTimeoutsDeclaredByJob(): array
{
    $declared = [];

    foreach (Finder::create()->files()->in(app_path())->path('/\/Jobs\//')->name('*.php') as $file) {
        $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

        if (! class_exists($class) || ! is_subclass_of($class, ShouldQueue::class)) {
            continue;
        }

        $attribute = new ReflectionClass($class)->getAttributes(Timeout::class)[0] ?? null;
        $declared[$class] = $attribute?->newInstance()->timeout;
    }

    return $declared;
}

it('finds the queued jobs', function (): void {
    expect(queueTimeoutsDeclaredByJob())->not->toBeEmpty();
});

it('stops every queued job before redis hands it to another worker', function (): void {
    $retryAfter = config()->integer('queue.connections.redis.retry_after');
    /** @var array<string, array{timeout: int}> $supervisors */
    $supervisors = config()->array('horizon.defaults');
    $supervisorTimeout = max(array_column($supervisors, 'timeout'));

    $tooLong = array_filter(
        queueTimeoutsDeclaredByJob(),
        static fn (?int $timeout): bool => ($timeout ?? $supervisorTimeout) >= $retryAfter,
    );

    expect($tooLong)->toBe([])
        ->and($supervisorTimeout)->toBeLessThan($retryAfter);
});
