<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Access\AccessSnapshot;
use App\Http\Requests\Stats\StatsRequest;
use App\Models\Guild;
use App\Stats\StatsQuery;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use Pentacore\Typefinder\Attributes\TypefinderPage;

/**
 * /stats: activity on the servers the user can see, one or all of them,
 * over the last 7, 30 or 365 days.
 */
final class StatsController extends Controller
{
    public const string MEMBER = '{ id: number; name: string; avatar_url: string; person_slug: string | null }';

    #[TypefinderPage(component: 'stats/Index', props: [
        'filters' => '{ guild: number | null; days: number }',
        'guilds' => '{ id: number; name: string }[]',
        'totals' => '{ messages: number; voice_seconds: number; reactions: number; members: number }',
        'heatmap' => '{ weekday: number; hour: number; count: number }[]',
        'messages' => '{ member: '.self::MEMBER.'; total: number }[]',
        'voice' => '{ member: '.self::MEMBER.'; total: number }[]',
        'reactors' => '{ member: '.self::MEMBER.'; total: number }[]',
        'voice_pairs' => '{ first: '.self::MEMBER.'; second: '.self::MEMBER.'; total: number }[]',
        'reaction_pairs' => '{ first: '.self::MEMBER.'; second: '.self::MEMBER.'; total: number }[]',
        'emoji' => '{ emoji: string; name: string; image_url: string | null; total: number }[]',
        'games' => '{ name: string; total: number; players: number }[]',
        'channels' => '{ channel_id: string; name: string; guild: string; total: number }[]',
        'records' => '{ longest_voice: { member: '.self::MEMBER.'; seconds: number; started_at: string } | null; busiest_day: { member: '.self::MEMBER.'; total: number; day: string } | null; longest_streak: { member: '.self::MEMBER.'; days: number; ended_on: string } | null }',
    ])]
    public function __invoke(StatsRequest $request): Response
    {
        $access = AccessSnapshot::forRequest($request) ?? throw new LogicException('Stats require a logged-in user.');
        $requested = $request->integer('guild');
        $guildId = $access->canSeeGuild($requested) ? $requested : null;
        $guildIds = $guildId !== null ? [$guildId] : $access->guildIds;
        $stats = new StatsQuery($guildIds, $request->days());

        return Inertia::render('stats/Index', [
            'filters' => ['guild' => $guildId, 'days' => $request->days()],
            'guilds' => array_values(Guild::query()->whereIn('id', $access->guildIds)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Guild $guild): array => ['id' => $guild->id, 'name' => $guild->name])->all()),
            'totals' => $stats->totals(),
            'heatmap' => $stats->heatmap(),
            'messages' => $stats->topMessagers(),
            'voice' => $stats->topVoice(),
            'reactors' => $stats->topReactors(),
            'voice_pairs' => $stats->voicePairs(),
            'reaction_pairs' => $stats->reactionPairs(),
            'emoji' => $stats->topEmoji(),
            'games' => $stats->games(),
            'channels' => $stats->topChannels(),
            'records' => $stats->records(),
        ]);
    }
}
