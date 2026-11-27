---
paths:
    - 'app/*/Jobs/**'
---

# Jobs

## Job payloads

Jobs carry scalar IDs, DTOs or raw payload arrays, never Eloquent models (no `SerializesModels`); re-query the model in `handle()`. Declare retries and uniqueness with attributes (`#[Tries]`, `#[UniqueFor]`, `#[Timeout]`).

## Timeouts stay below retry_after

Redis hands a reserved job to a second worker once `retry_after` (`config/queue.php`, 630 s) passes, even while the first is still running it. Every job must stop before that: with its own `#[Timeout]`, or with the Horizon supervisor's `timeout` (60 s) when it declares none. A job that needs longer than 600 s raises `retry_after` too. `tests/Unit/Architecture/QueueTimeoutsTest.php` enforces it.

## Scheduled jobs that must not overlap are unique

`withoutOverlapping()` does nothing useful on `Schedule::job()`: the scheduler releases its lock as soon as the job is queued, so two runs of the job can still overlap on the workers. A scheduled job that must not run twice at once (`SendEventReminders` could DM a reminder twice) implements `ShouldBeUnique` with `#[UniqueFor]` at least its timeout and short enough that a crashed run costs a tick or two. `tests/Feature/ScheduleTest.php` fails on `withoutOverlapping()` on a scheduled job.

## New constructor fields on a queued job

A job already on the queue when you deploy is unserialized without any field added since, and a promoted property never gets its default there, so reading it throws "must not be accessed before initialization". Add a new field as a nullable property with a default, declared outside the constructor and assigned in it, and treat `null` as "queued before this existed". Rector's `ClassPropertyAssignToConstructorPromotionRector` promotes it straight back, so add the job to that rule's skip list in `rector.php` (`->withSkip([ClassPropertyAssignToConstructorPromotionRector::class => [__DIR__.'/app/…/Jobs/TheJob.php']])`).
