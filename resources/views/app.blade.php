<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">
        <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#141414" media="(prefers-color-scheme: dark)">

        @fonts

        @php
            $reverbApp = config('reverb.apps.apps.0', []);
            $reverbOptions = $reverbApp['options'] ?? [];
            $appUrlHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        @endphp
        {{-- Where browsers reach Reverb, read by app.ts at runtime (config/reverb.php "browser"). --}}
        <meta name="reverb-config" content="{{ json_encode([
            'key' => $reverbApp['key'] ?? null,
            'host' => config('reverb.browser.host') ?? ($appUrlHost ?: ($reverbOptions['host'] ?? null)),
            'port' => (int) (config('reverb.browser.port') ?? $reverbOptions['port'] ?? 443),
            'scheme' => config('reverb.browser.scheme') ?? $reverbOptions['scheme'] ?? 'https',
        ]) }}">

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
