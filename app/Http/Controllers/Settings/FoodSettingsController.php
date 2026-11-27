<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateDietaryNotesRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use Pentacore\Typefinder\Attributes\TypefinderPage;

/**
 * /settings/food: allergies and food preferences, set once and shown to
 * the organisers of events you RSVP to.
 */
final class FoodSettingsController extends Controller
{
    #[TypefinderPage(component: 'settings/Food', props: [
        'dietary_notes' => 'string',
    ])]
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Food', [
            'dietary_notes' => $this->user($request)->discordUser->dietary_notes ?? '',
        ]);
    }

    public function update(UpdateDietaryNotesRequest $request): RedirectResponse
    {
        $notes = trim($request->string('dietary_notes')->value());
        $this->user($request)->discordUser->forceFill(['dietary_notes' => $notes !== '' ? $notes : null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        return to_route('food-settings.edit');
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new LogicException('Settings require a logged-in user.');
    }
}
