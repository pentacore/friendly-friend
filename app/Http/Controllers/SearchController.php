<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Access\AccessSnapshot;
use App\Http\Requests\GlobalSearchRequest;
use App\Http\Resources\SearchResultsResource;
use App\Models\Event;
use App\Models\Person;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * The header search box (Ctrl+K, JSON for `useHttp`): a few of each kind
 * of thing the user can see. Quotes go through the shared quote search
 * (close spellings when nothing matches exactly); people and events match
 * their name or title anywhere, ignoring accents.
 */
final class SearchController extends Controller
{
    /** Results per kind; the quote feed has the rest. */
    private const int PER_KIND = 5;

    public function __invoke(GlobalSearchRequest $request): SearchResultsResource
    {
        $access = AccessSnapshot::forRequest($request) ?? throw new LogicException('Search requires a logged-in user.');
        $search = $request->search();
        $pattern = $request->pattern();

        $quotes = Quote::query()->visibleTo($access)->with('lines');
        $closeMatches = Quote::applySearch($quotes, $search);

        return new SearchResultsResource(
            $quotes->orderByRelevance($search)->orderByDesc('published_at')->limit(self::PER_KIND)->get(),
            Person::query()
                ->whereHas('quoteLines.quote', fn (Builder $visible) => $visible->visibleTo($access))
                ->where(fn (Builder $matches) => $matches
                    ->whereRaw('f_unaccent(display_name) ilike f_unaccent(?)', [$pattern])
                    ->orWhereHas('aliases', fn (Builder $aliases) => $aliases->whereRaw('f_unaccent(alias) ilike f_unaccent(?)', [$pattern])))
                ->orderByRaw('similarity(f_unaccent(lower(display_name)), f_unaccent(lower(?))) desc', [$search])
                ->orderBy('display_name')
                ->limit(self::PER_KIND)
                ->get(['id', 'slug', 'display_name']),
            Event::query()
                ->whereIn('guild_id', $access->guildIds)
                ->where(fn (Builder $matches) => $matches
                    ->whereRaw('f_unaccent(title) ilike f_unaccent(?)', [$pattern])
                    ->orWhereRaw('f_unaccent(location) ilike f_unaccent(?)', [$pattern]))
                ->orderByDesc('starts_at')
                ->limit(self::PER_KIND)
                ->get(['id', 'title', 'starts_at']),
            $closeMatches,
        );
    }
}
