<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Access\AccessSnapshot;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use App\Stats\StatsQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use Pentacore\Typefinder\Attributes\TypefinderPage;

/**
 * Home after login: a random quote up front, then the latest ones.
 */
final class DashboardController extends Controller
{
    private const int RECENT = 5;

    #[TypefinderPage(component: 'Dashboard', props: [
        'random' => QuoteResource::class.' | null',
        'recent' => QuoteResource::class.'[]',
        'total' => 'number',
        'week' => '{ messages: number; voice_seconds: number; members: number; top: { name: string; avatar_url: string; total: number } | null } | null',
    ])]
    public function __invoke(Request $request): Response
    {
        $access = AccessSnapshot::forRequest($request) ?? throw new LogicException('The dashboard requires a logged-in user.');
        $visible = fn () => Quote::query()->visibleTo($access)->with(QuoteController::cardRelations($access));
        $random = $visible()->inRandomOrder()->first();

        return Inertia::render('Dashboard', [
            'random' => $random !== null ? new QuoteResource($random) : null,
            'recent' => QuoteResource::collection($visible()->latest('published_at')->latest('id')->limit(self::RECENT)->get()),
            'total' => Quote::query()->visibleTo($access)->count(),
            'week' => fn (): ?array => $this->week($access),
        ]);
    }

    /**
     * The last 7 days on the user's servers, for the "this week" card; null
     * when nothing was counted.
     *
     * @return array{messages: int, voice_seconds: int, members: int, top: array{name: string, avatar_url: string, total: int}|null}|null
     */
    private function week(AccessSnapshot $access): ?array
    {
        $stats = new StatsQuery($access->guildIds, 7);
        $totals = $stats->totals();

        if ($totals['messages'] === 0 && $totals['voice_seconds'] === 0) {
            return null;
        }

        $top = $stats->topMessagers(1)[0] ?? null;

        return [
            'messages' => $totals['messages'],
            'voice_seconds' => $totals['voice_seconds'],
            'members' => $totals['members'],
            'top' => $top !== null ? ['name' => $top['member']['name'], 'avatar_url' => $top['member']['avatar_url'], 'total' => $top['total']] : null,
        ];
    }
}
