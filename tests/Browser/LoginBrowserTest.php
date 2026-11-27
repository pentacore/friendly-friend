<?php

declare(strict_types=1);

use Laravel\Socialite\Facades\Socialite;

/**
 * Regression: the login page's error message once never showed because its
 * props compiled away (stale Vue type cache).
 */
it('explains why a login failed', function (bool $dark): void {
    Socialite::shouldReceive('driver->user')->andThrow(new RuntimeException('invalid state'));

    $page = visit('/auth/discord/callback');
    $page = $dark ? $page->inDarkMode() : $page;

    $page->assertSee('Log in with Discord')
        ->assertSee('Discord login was cancelled or failed')
        ->assertNoJavaScriptErrors();
    $page->script('document.fonts.ready');
    $page->screenshot(filename: 'login-error-'.($dark ? 'dark' : 'light'));
})->with(['light' => false, 'dark' => true]);
