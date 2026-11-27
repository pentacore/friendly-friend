<?php

declare(strict_types=1);

use Laravel\Boost\Rules\RuleFrontmatter;
use Symfony\Component\Finder\Finder;

/**
 * Agents open a `.ai/rules` file only when one of its `paths:` globs matches
 * the file they edit, so a glob that matches nothing hides its rules without
 * a word. Every glob must match at least one project file. Files the root
 * `.gitignore` lists (vendor, the generated Wayfinder routes) never keep a
 * glob alive. It walks the disk and reads `.gitignore` itself instead of
 * asking git, because a worktree's git directory is a host path the Sail
 * container can't see.
 *
 * `**` spans any number of path segments, `*` and `?` stay within one.
 *
 * @return list<string>
 */
function ruleGlobsProjectFiles(): array
{
    static $files = null;

    if ($files !== null) {
        return $files;
    }

    $root = dirname(__DIR__, 3);
    $ignored = array_filter(
        array_map(trim(...), file($root.'/.gitignore') ?: []),
        static fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#'),
    );

    $isIgnored = static fn (string $path): bool => array_any($ignored, static fn (string $pattern): bool => str_starts_with($pattern, '/')
        ? fnmatch(ltrim($pattern, '/'), $path) || str_starts_with($path, ltrim($pattern, '/').'/')
        : fnmatch($pattern, basename($path)));

    $finder = Finder::create()->files()->in($root)
        ->exclude(['vendor', 'node_modules', 'storage', '.git'])
        ->ignoreDotFiles(false)
        ->ignoreVCS(true);

    $files = [];

    foreach ($finder as $file) {
        $path = str_replace('\\', '/', $file->getRelativePathname());

        if (! $isIgnored($path)) {
            $files[] = $path;
        }
    }

    return $files;
}

function ruleGlobRegex(string $glob): string
{
    $regex = '';

    for ($i = 0, $length = strlen($glob); $i < $length; $i++) {
        $char = $glob[$i];

        if ($char === '*' && ($glob[$i + 1] ?? '') === '*') {
            $i++;

            if (($glob[$i + 1] ?? '') === '/') {
                $i++;
                $regex .= '(?:.*/)?';
            } else {
                $regex .= '.*';
            }

            continue;
        }

        $regex .= match ($char) {
            '*' => '[^/]*',
            '?' => '[^/]',
            default => preg_quote($char, '#'),
        };
    }

    return sprintf('#^%s$#', $regex);
}

/**
 * @param  list<string>  $files
 */
function ruleGlobMatchesAny(string $glob, array $files): bool
{
    $regex = ruleGlobRegex($glob);

    return array_any($files, static fn (string $file): bool => preg_match($regex, $file) === 1);
}

/**
 * @return array<string, array{string}>
 */
function ruleGlobsRuleFiles(): array
{
    $rules = [];

    foreach (glob(dirname(__DIR__, 3).'/.ai/rules/*.md') ?: [] as $path) {
        if (basename($path) !== 'index.md') {
            $rules[basename($path)] = [$path];
        }
    }

    return $rules;
}

it('matches at least one project file with every rule glob', function (string $path): void {
    $globs = RuleFrontmatter::parse((string) file_get_contents($path))['paths'];

    expect($globs)->not->toBeEmpty();

    $dead = array_values(array_filter($globs, static fn (string $glob): bool => ! ruleGlobMatchesAny($glob, ruleGlobsProjectFiles())));

    expect($dead)->toBe([]);
})->with(ruleGlobsRuleFiles());

it('reads globs the way the rules mean them', function (string $glob, string $path, bool $matches): void {
    expect(ruleGlobMatchesAny($glob, [$path]))->toBe($matches);
})->with([
    ['**', 'app/Models/User.php', true],
    ['app/*/Jobs/**', 'app/Quotes/Jobs/ImportChannelQuotes.php', true],
    ['app/*/Jobs/**', 'app/Quotes/Imports/Jobs/Deep.php', false],
    ['app/Models/Event*.php', 'app/Models/EventRsvp.php', true],
    ['app/Models/Event*.php', 'app/Models/Sub/EventRsvp.php', false],
    ['resources/js/pages/events/**', 'resources/js/pages/events/show/Costs.vue', true],
    ['tests/**', 'tests/Pest.php', true],
    ['app/Models/Quote?.php', 'app/Models/Quotes.php', true],
]);

it('leaves files git ignores out of the project files', function (): void {
    expect(ruleGlobMatchesAny('app/Models/*.php', ruleGlobsProjectFiles()))->toBeTrue()
        ->and(ruleGlobMatchesAny('app/NoSuchFolder/**', ruleGlobsProjectFiles()))->toBeFalse()
        ->and(ruleGlobMatchesAny('vendor/**', ruleGlobsProjectFiles()))->toBeFalse()
        ->and(ruleGlobMatchesAny('.env', ruleGlobsProjectFiles()))->toBeFalse();
});
