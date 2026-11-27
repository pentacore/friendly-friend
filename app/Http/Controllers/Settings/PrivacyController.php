<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Access\PersonalData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePrivacyRequest;
use App\Models\User;
use App\Stats\Actions\OptIntoStats;
use App\Stats\Actions\OptOutOfStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Pentacore\Typefinder\Attributes\TypefinderPage;

/**
 * /settings/privacy: see whether stats are counted for you, turn them off
 * (deleting what was counted) or back on, and download your data.
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
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Stats are off. What was counted is being deleted.')]);
        }

        return to_route('privacy.edit');
    }

    /**
     * Everything stored about you, as a JSON download.
     */
    public function download(Request $request): JsonResponse
    {
        return response()->json(PersonalData::for($this->user($request)), 200, [
            'Content-Disposition' => 'attachment; filename="friendly-friend-data-'.now()->toDateString().'.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
