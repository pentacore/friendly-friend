<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;

afterEach(function (): void {
    TrustProxies::flushState();
});

function bootProviders(): void
{
    app()->getProvider(AppServiceProvider::class)?->boot();
}

it('uses the forwarded scheme and host behind a trusted proxy', function (): void {
    config()->set('app.trusted_proxies', '*');
    bootProviders();

    $response = $this->get('/dashboard', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'quotes.example.test', 'X-Forwarded-Port' => '443']);

    // Raw header: assertRedirect() would accept a relative or http URL too.
    expect($response->headers->get('Location'))->toBe('https://quotes.example.test/login');
});

it('ignores forwarded headers when no proxy is trusted', function (): void {
    config()->set('app.trusted_proxies', '');
    bootProviders();

    $response = $this->get('/dashboard', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.example.test']);

    expect($response->headers->get('Location'))->toBe('http://localhost/login');
});
