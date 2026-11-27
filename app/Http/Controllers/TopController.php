<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Access\AccessSnapshot;
use App\Http\Resources\QuoteResource;
use App\Models\DiscordUser;
use App\Models\Person;
use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use Pentacore\Typefinder\Attributes\TypefinderPage;

/**
 * Top lists over the quotes the user can see.
 */
final class TopController extends Controller
{
    private const int SIZE = 10;

    /** Ratings only mean something after a few this-or-that rounds. */
    private const int MIN_MATCHUPS = 3;

    #[TypefinderPage(component: 'quotes/Top', props: [
        'all_time' => QuoteResource::class.'[]',
        'this_month' => QuoteResource::class.'[]',
        'elo' => QuoteResource::class.'[]',
        'most_quoted' => '{ slug: string; display_name: string; quote_count: number }[]',
        'top_submitters' => '{ name: string; avatar_url: string; quote_count: number }[]',
    ])]
    public function __invoke(Request $request): Response
    {
        $access = AccessSnapshot::forRequest($request) ?? throw new LogicException('Top lists require a logged-in user.');
        $visible = fn () => Quote::query()->visibleTo($access)->with(QuoteController::cardRelations($access));

        return Inertia::render('quotes/Top', [
            'all_time' => QuoteResource::collection($visible()->orderByDesc('score')->orderByDesc('published_at')->limit(self::SIZE)->get()),
            'this_month' => QuoteResource::collection($visible()->where('published_at', '>=', now()->startOfMonth())->orderByDesc('score')->orderByDesc('published_at')->limit(self::SIZE)->get()),
            'elo' => QuoteResource::collection($visible()->where('matchup_count', '>=', self::MIN_MATCHUPS)->orderByDesc('elo')->limit(self::SIZE)->get()),
            'most_quoted' => $this->mostQuoted($access),
            'top_submitters' => $this->topSubmitters($access),
        ]);
    }

    /**
     * @return list<array{slug: string, display_name: string, quote_count: int}>
     */
    private function mostQuoted(AccessSnapshot $access): array
    {
        $rows = DB::table('quote_lines')
            ->joinSub(Quote::query()->visibleTo($access)->select('id'), 'visible', 'visible.id', '=', 'quote_lines.quote_id')
            ->whereNotNull('person_id')
            ->groupBy('person_id')
            ->selectRaw('person_id, count(distinct quote_lines.quote_id) as quote_count')
            ->orderByDesc('quote_count')
            ->limit(self::SIZE)
            ->get();

        $people = Person::query()->whereKey($rows->pluck('person_id')->all())->get()->keyBy('id');
        $ranked = [];

        foreach ($rows as $row) {
            $person = is_numeric($row->person_id ?? null) ? $people->get((int) $row->person_id) : null;

            if ($person instanceof Person && is_numeric($row->quote_count ?? null)) {
                $ranked[] = ['slug' => $person->slug, 'display_name' => $person->display_name, 'quote_count' => (int) $row->quote_count];
            }
        }

        return $ranked;
    }

    /**
     * @return list<array{name: string, avatar_url: string, quote_count: int}>
     */
    private function topSubmitters(AccessSnapshot $access): array
    {
        $rows = Quote::query()->visibleTo($access)->toBase()
            ->groupBy('submitted_by_discord_user_id')
            ->selectRaw('submitted_by_discord_user_id, count(*) as quote_count')
            ->orderByDesc('quote_count')
            ->limit(self::SIZE)
            ->get();

        $users = DiscordUser::query()->whereKey($rows->pluck('submitted_by_discord_user_id')->all())->get()->keyBy('id');
        $ranked = [];

        foreach ($rows as $row) {
            $user = is_numeric($row->submitted_by_discord_user_id ?? null) ? $users->get((int) $row->submitted_by_discord_user_id) : null;

            if ($user instanceof DiscordUser && is_numeric($row->quote_count ?? null)) {
                $ranked[] = ['name' => $user->display_name, 'avatar_url' => $user->avatarUrl(), 'quote_count' => (int) $row->quote_count];
            }
        }

        return $ranked;
    }
}
