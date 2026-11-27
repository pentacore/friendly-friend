<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Planning\Calendar;
use App\Planning\CalendarFeed;
use Illuminate\Http\Response;

/**
 * The personal calendar feed (`/calendar/{token}.ics`). Calendar apps fetch
 * it without a session, so the secret token in the URL is the only key;
 * resetting it in settings breaks the old link.
 */
final class CalendarFeedController extends Controller
{
    public function __invoke(string $token): Response
    {
        $user = User::query()->where('calendar_token', $token)->first();

        abort_unless($user instanceof User, 404);

        return response(Calendar::feed(CalendarFeed::eventsFor($user), config()->string('app.name')), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Cache-Control' => 'private, max-age=900',
        ]);
    }
}
