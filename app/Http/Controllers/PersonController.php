<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Access\AccessSnapshot;
use App\Http\Resources\QuoteResource;
use App\Models\Person;
use App\Models\PersonAlias;
use App\Models\Quote;
use App\Stats\StatsQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use Pentacore\Typefinder\Attributes\TypefinderPage;

/**
 * A person's page: who they are, their visible quotes and (unless they
 * opted out) their stats.
 */
final class PersonController extends Controller
{
    #[TypefinderPage(component: 'people/Show', props: [
        'person' => '{ id: number; slug: string; display_name: string; aliases: string[]; avatar_url: string | null; quote_count: number }',
        'quotes' => 'SimplePaginatedResourceCollection<'.QuoteResource::class.'>',
        'stats' => '{ days: number; totals: { messages: number; voice_seconds: number; reactions: number }; heatmap: { weekday: number; hour: number; count: number }[]; emoji: { emoji: string; name: string; image_url: string | null; total: number }[]; games: { name: string; total: number; players: number }[]; voice_pairs: { first: '.StatsController::MEMBER.'; second: '.StatsController::MEMBER.'; total: number }[] } | null',
    ])]
    public function show(Request $request, Person $person): Response
    {
        $access = AccessSnapshot::forRequest($request) ?? throw new LogicException('People pages require a logged-in user.');

        $quotes = Quote::query()
            ->visibleTo($access)
            ->whereHas('lines', fn (Builder $lines) => $lines->where('person_id', $person->id))
            ->with(QuoteController::cardRelations($access))
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        abort_if((clone $quotes)->doesntExist() && $person->discordUser === null, 404);

        return Inertia::render('people/Show', [
            'person' => [
                'id' => $person->id,
                'slug' => $person->slug,
                'display_name' => $person->display_name,
                'aliases' => array_values($person->aliases->map(fn (PersonAlias $alias): string => $alias->alias)->all()),
                'avatar_url' => $person->discordUser?->avatarUrl(),
                'quote_count' => (clone $quotes)->count(),
            ],
            'quotes' => Inertia::scroll(QuoteResource::collection($quotes->simplePaginate(20))),
            'stats' => $this->stats($person, $access),
        ]);
    }

    /**
     * Their last 30 days on the servers the viewer can see. Null for people
     * outside Discord and for members who opted out.
     *
     * @return array{days: int, totals: array{messages: int, voice_seconds: int, reactions: int}, heatmap: list<array{weekday: int, hour: int, count: int}>, emoji: list<array{emoji: string, name: string, image_url: string|null, total: int}>, games: list<array{name: string, total: int, players: int}>, voice_pairs: list<array{first: array{id: int, name: string, avatar_url: string, person_slug: string|null}, second: array{id: int, name: string, avatar_url: string, person_slug: string|null}, total: int}>}|null
     */
    private function stats(Person $person, AccessSnapshot $access): ?array
    {
        $member = $person->discordUser;

        if ($member === null || $member->tracking_opted_out_at !== null) {
            return null;
        }

        $days = 30;
        $stats = new StatsQuery($access->guildIds, $days);
        ['messages' => $messages, 'voice_seconds' => $voiceSeconds, 'reactions' => $reactions] = $stats->totals($member->id);

        return [
            'days' => $days,
            'totals' => ['messages' => $messages, 'voice_seconds' => $voiceSeconds, 'reactions' => $reactions],
            'heatmap' => $stats->heatmap($member->id),
            'emoji' => $stats->topEmoji($member->id, 5),
            'games' => $stats->games($member->id, 5),
            'voice_pairs' => $stats->voicePairs($member->id, 5),
        ];
    }
}
