<?php

declare(strict_types=1);

use App\Planning\Jobs\SendEventReminders;
use App\Quotes\Jobs\PruneExpiredDrafts;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;

it('never relies on withoutOverlapping() for a scheduled job', function (): void {
    // Schedule::job() releases that lock as soon as the job is queued, so it
    // never stops two runs of the job from overlapping. ShouldBeUnique does.
    $overlapping = collect(resolve(Schedule::class)->events())
        ->filter(fn (Event $event): bool => $event instanceof CallbackEvent && $event->withoutOverlapping)
        ->map(fn (Event $event): ?string => $event->description)
        ->values()
        ->all();

    expect($overlapping)->toBe([]);
});

it('queues a frequent scheduled job only once while a run is still waiting', function (string $job): void {
    Queue::fake();

    dispatch(new $job);
    dispatch(new $job);

    Queue::assertPushedTimes($job, 1);
})->with([
    'event reminders' => [SendEventReminders::class],
    'draft pruning' => [PruneExpiredDrafts::class],
]);
