<?php

declare(strict_types=1);

use Illuminate\Routing\Route;
use Tests\Support\LayoutGate;

it('puts every named page route in the browser layout gate or says why not', function (): void {
    $unclassified = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => in_array('GET', $route->methods(), true)
            && in_array('web', $route->gatherMiddleware(), true)
            && $route->getName() !== null
            && ! str_starts_with($route->uri(), '_')
            && ! str_starts_with((string) $route->getName(), 'horizon.')
            && ! str_starts_with((string) $route->getName(), 'telescope'))
        ->map(fn (Route $route): string => (string) $route->getName())
        ->reject(fn (string $name): bool => array_key_exists($name, LayoutGate::PAGES) || array_key_exists($name, LayoutGate::EXCLUDED))
        ->values()
        ->all();

    expect($unclassified)->toBe([]);
});

it('lists no route the app doesn\'t have', function (): void {
    $missing = array_values(array_filter(
        [...array_keys(LayoutGate::PAGES), ...array_keys(LayoutGate::EXCLUDED)],
        fn (string $name): bool => ! app('router')->has($name),
    ));

    expect($missing)->toBe([]);
});
