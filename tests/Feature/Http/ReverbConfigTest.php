<?php

declare(strict_types=1);

/**
 * Browsers learn where Reverb is from the page, at runtime, so one built
 * image works for every deploy (`<meta name="reverb-config">`, read by app.ts).
 */
function reverbConfigFromPage(string $html): mixed
{
    preg_match('/<meta name="reverb-config" content="([^"]+)">/', $html, $matches);

    return json_decode(html_entity_decode($matches[1] ?? 'null'), true);
}

it('tells browsers the public Reverb endpoint, not the one inside Docker', function (): void {
    config()->set('reverb.apps.apps.0.key', 'public-key');
    config()->set('reverb.apps.apps.0.options.host', 'reverb');
    config()->set('reverb.browser', ['host' => 'ws.quotes.example.com', 'port' => 443, 'scheme' => 'https']);

    expect(reverbConfigFromPage($this->get(route('login'))->assertOk()->getContent() ?: ''))
        ->toBe(['key' => 'public-key', 'host' => 'ws.quotes.example.com', 'port' => 443, 'scheme' => 'https']);
});

it('falls back to the site host when no public Reverb host is set', function (): void {
    config()->set('app.url', 'https://quotes.example.com');
    config()->set('reverb.apps.apps.0.key', 'public-key');
    config()->set('reverb.browser', ['host' => null, 'port' => null, 'scheme' => null]);

    expect(reverbConfigFromPage($this->get(route('login'))->assertOk()->getContent() ?: ''))
        ->toMatchArray(['host' => 'quotes.example.com']);
});
