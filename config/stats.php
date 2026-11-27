<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    |
    | Days (reaction buckets, daily rollups) and the weekday × hour heatmap
    | use this timezone. Timestamps are stored in UTC.
    |
    */

    'timezone' => (string) env('STATS_TIMEZONE', 'Europe/Stockholm'),

    /*
    |--------------------------------------------------------------------------
    | Tracked activities
    |--------------------------------------------------------------------------
    |
    | Discord activity types counted as "playing" sessions (0 = Playing).
    | Spotify (2), custom statuses (4) and the rest are ignored.
    |
    */

    'activity_types' => [0],

];
