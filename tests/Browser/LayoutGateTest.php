<?php

declare(strict_types=1);

use Tests\Support\LayoutGate;

/**
 * Every page on a phone (375×812) and a desktop (MacBook Air), in light and
 * dark mode: no JavaScript errors, nothing wider than the screen, the dark
 * theme applied when the browser asks for it, and every visible button and
 * link named. A new page goes in `Tests\Support\LayoutGate::PAGES`
 * (`LayoutGateRoutesTest` fails until it is there).
 */
it('lays out every page on every screen', function (string $routeName, string $screen, string $scheme): void {
    ['user' => $user, 'parameters' => $parameters] = LayoutGate::seed();

    if ($routeName !== 'login') {
        $this->actingAs($user);
    }

    $parameter = LayoutGate::PAGES[$routeName];
    $url = route($routeName, $parameter === null ? [] : [$parameter => $parameters[$parameter]], absolute: false);

    $pending = visit($url);
    $pending = $scheme === 'dark' ? $pending->inDarkMode() : $pending->inLightMode();
    $page = $screen === 'phone' ? $pending->on()->mobile() : $pending->on()->macbookAir();

    $page->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.classList.contains("dark")', $scheme === 'dark')
        ->assertScript(LayoutGate::OVERFLOW_SCRIPT, '')
        ->assertScript(LayoutGate::UNNAMED_CONTROL_SCRIPT, '');

    $page->script('document.fonts.ready');
    $page->screenshot(fullPage: true, filename: sprintf('gate-%s-%s-%s', str_replace('.', '-', $routeName), $screen, $scheme));
})->with(array_keys(LayoutGate::PAGES))->with(['phone', 'desktop'])->with(['light', 'dark']);
