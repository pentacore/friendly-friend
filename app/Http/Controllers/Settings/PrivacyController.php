<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePrivacyRequest;
use App\Models\User;
use App\Stats\Actions\OptIntoStats;
use App\Stats\Actions\OptOutOfStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Pentacore\Typefinder\Attributes\TypefinderPage;

/**
 * /settings/privacy: see whether stats are counted for you, and turn them
 * off (deleting what was counted) or back on.
 */
final class PrivacyController extends Controller
{
    #[TypefinderPage(component: 'settings/Privacy', props: [
        'opted_out_at' => 'string | null',
    ])]
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Privacy', [
            'opted_out_at' => $this->user($request)->discordUser->tracking_opted_out_at?->toIso8601String(),
        ]);
    }

    public function update(UpdatePrivacyRequest $request, OptOutOfStats $optOut, OptIntoStats $optIn): RedirectResponse
    {
        $member = $this->user($request)->discordUser;

        if ($request->boolean('tracking')) {
            $optIn->handle($member);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Stats are on again.')]);
        } else {
            $optOut->handle($member);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Stats are off, and your stats are being deleted.')]);
        }

        return to_route('privacy.edit');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
