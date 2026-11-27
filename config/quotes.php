<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | AI cleanup of captured quotes
    |--------------------------------------------------------------------------
    |
    | Providers tried in order (failover), from laravel/ai's config/ai.php.
    | Providers without an API key are skipped; with none left, quotes are
    | parsed without AI (PlainQuoteNormalizer). Capture never depends on AI.
    |
    */

    'ai' => [
        'providers' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env('QUOTES_AI_PROVIDERS', 'anthropic,openai,gemini')),
        ))),
        'timeout' => (int) env('QUOTES_AI_TIMEOUT', 15),
    ],

    /*
    | Minutes before an unsaved draft is discarded. Kept under Discord's 15
    | minute interaction token lifetime, so the private preview can still be
    | edited to say "expired" when the draft goes.
    */

    'draft_ttl_minutes' => 14,

    /*
    | Timezone used to understand "yesterday" etc. and to show times.
    */

    'timezone' => env('QUOTES_TIMEZONE', 'Europe/Stockholm'),

];
