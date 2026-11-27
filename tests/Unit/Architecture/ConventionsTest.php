<?php

declare(strict_types=1);

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Finder\Finder;

/**
 * The "never do X" rules from `.ai/rules/app.md`, `general.md` and
 * `tests.md`, checked so they can't drift into prose only.
 */
arch('every file declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('classes are final, except models, providers, middleware and the base controller')
    ->expect('App')
    ->classes()
    ->toBeFinal()
    ->ignoring(['App\Models', 'App\Providers', 'App\Http\Middleware', Controller::class]);

arch('env() is read only in config files')
    ->expect('App')
    ->not->toUse(['env']);

arch('jobs carry IDs and payloads, never serialized models')
    ->expect('App')
    ->not->toUse(SerializesModels::class);

arch('events exist only to broadcast')
    ->expect('App\Events')
    ->toImplement(ShouldBroadcast::class);

/**
 * @return array<string, string> project-relative path => contents
 */
function conventionsPhpFiles(string ...$directories): array
{
    $root = dirname(__DIR__, 3);
    $files = [];

    foreach (Finder::create()->files()->in(array_map(static fn (string $directory): string => $root.'/'.$directory, $directories))->name('*.php') as $file) {
        $files[str_replace($root.'/', '', $file->getPathname())] = $file->getContents();
    }

    return $files;
}

it('has no events/listeners, services or repositories layer', function (): void {
    $root = dirname(__DIR__, 3);

    expect(is_dir($root.'/app/Listeners'))->toBeFalse()
        ->and(is_dir($root.'/app/Services'))->toBeFalse()
        ->and(is_dir($root.'/app/Repositories'))->toBeFalse();
});

it('starts every Eloquent query with Model::query()', function (): void {
    $models = [];

    foreach (conventionsPhpFiles('app/Models') as $path => $contents) {
        $class = 'App\\Models\\'.basename($path, '.php');

        if (is_subclass_of($class, Model::class)) {
            $models[class_basename($class)] = $class;
        }
    }

    $offenders = [];

    foreach (conventionsPhpFiles('app', 'routes', 'database', 'tests') as $path => $contents) {
        preg_match_all('/\b('.implode('|', array_keys($models)).')::([a-z]\w*)\(/', $contents, $matches, PREG_SET_ORDER);

        foreach ($matches as [, $model, $method]) {
            // Only the model itself: `Event::fake()` is the Event facade unless the file imports App\Models\Event.
            $refersToModel = str_contains($contents, 'use '.$models[$model].';') || str_contains($contents, 'namespace App\\Models;');

            if (! $refersToModel) {
                continue;
            }

            // A real static method (query(), factory(), a model's own helper) is fine;
            // anything else reaches the query builder through __callStatic.
            $isRealStatic = method_exists($models[$model], $method) && new ReflectionMethod($models[$model], $method)->isStatic();

            if (! $isRealStatic) {
                $offenders[] = sprintf('%s: %s::%s()', $path, $model, $method);
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('dispatches jobs with the dispatch() helper', function (): void {
    $offenders = [];

    foreach (conventionsPhpFiles('app', 'routes') as $path => $contents) {
        if (preg_match('/\b[A-Z]\w+::dispatch(Sync|If|Unless|AfterResponse)?\(/', $contents) === 1) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

it('uses framework fakes and Fake* classes, never Mockery', function (): void {
    $offenders = array_keys(array_filter(
        conventionsPhpFiles('tests', 'app'),
        static fn (string $contents): bool => preg_match('/\bMockery\b|->(mock|partialMock|spy)\(/', $contents) === 1,
    ));

    expect(array_values(array_diff($offenders, ['tests/Unit/Architecture/ConventionsTest.php'])))->toBe([]);
});
