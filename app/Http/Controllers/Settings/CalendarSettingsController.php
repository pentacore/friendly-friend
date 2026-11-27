<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use Pentacore\Typefinder\Attributes\TypefinderPage;

/**
 * /settings/calendar: the personal calendar feed link, to create, reset
 * (the old link stops working) or turn off.
 */
final class CalendarSettingsController extends Controller
{
    #[TypefinderPage(component: 'settings/Calendar', props: [
        'feed_url' => 'string | null',
    ])]
    public function edit(Request $request): Response
    {
        $token = $this->user($request)->calendar_token;

        return Inertia::render('settings/Calendar', [
            'feed_url' => $token !== null ? route('calendar.feed', $token) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->user($request)->forceFill(['calendar_token' => Str::random(40)])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('New calendar link created.')]);

        return to_route('calendar-settings.edit');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->user($request)->forceFill(['calendar_token' => null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Calendar link turned off.')]);

        return to_route('calendar-settings.edit');
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new LogicException('Settings require a logged-in user.');
    }
}
