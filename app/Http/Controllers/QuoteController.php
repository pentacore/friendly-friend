<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Access\AccessSnapshot;
use App\Enums\QuoteStatus;
use App\Http\Requests\Quotes\QuoteIndexRequest;
use App\Http\Requests\Quotes\UpdateQuoteRequest;
use App\Http\Resources\QuoteResource;
use App\Http\Resources\QuoteRevisionResource;
use App\Models\Guild;
use App\Models\Person;
use App\Models\Quote;
use App\Models\User;
use App\Quotes\Actions\EditQuote;
use App\Quotes\Actions\RemoveQuote;
use App\Quotes\Actions\RestoreQuote;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use Pentacore\Typefinder\Attributes\TypefinderPage;

/**
 * The quote feed, quote pages, editing and removal on the website.
 */
final class QuoteController extends Controller
{
    private const int PER_PAGE = 20;

    #[TypefinderPage(component: 'quotes/Index', props: [
        'quotes' => 'SimplePaginatedResourceCollection<'.QuoteResource::class.'>',
        'filters' => '{ q: string; guild: number | null; person: string | null }',
        'guilds' => '{ id: number; name: string }[]',
        'person' => '{ slug: string; display_name: string } | null',
    ])]
    public function index(QuoteIndexRequest $request): Response
    {
        $access = $this->access($request);
        $search = trim($request->string('q')->value());
        $guildId = $request->integer('guild') ?: null;
        $person = $request->filled('person') ? Person::query()->where('slug', $request->string('person')->value())->first() : null;

        $quotes = Quote::query()->visibleTo($access)->with(self::cardRelations($access))
            ->when($guildId !== null, fn (Builder $query) => $query->where('guild_id', $guildId))
            ->when($person !== null, fn (Builder $query) => $query->whereHas('lines', fn (Builder $lines) => $lines->where('person_id', $person?->id)))
            ->when($search !== '', fn (Builder $query) => $query->whereRaw("search_vector @@ websearch_to_tsquery('simple', ?)", [$search]))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->simplePaginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('quotes/Index', [
            'quotes' => Inertia::scroll(QuoteResource::collection($quotes)),
            'filters' => ['q' => $search, 'guild' => $guildId, 'person' => $person?->slug],
            'guilds' => Guild::query()->whereIn('id', $access->guildIds)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Guild $guild): array => ['id' => $guild->id, 'name' => $guild->name])->values()->all(),
            'person' => $person !== null ? ['slug' => $person->slug, 'display_name' => $person->display_name] : null,
        ]);
    }

    #[TypefinderPage(component: 'quotes/Show', props: [
        'quote' => QuoteResource::class,
        'revisions' => QuoteRevisionResource::class.'[]',
    ])]
    public function show(Request $request, Quote $quote): Response
    {
        Gate::authorize('view', $quote);
        $access = $this->access($request);

        $quote->load(self::cardRelations($access));

        return Inertia::render('quotes/Show', [
            'quote' => new QuoteResource($quote),
            'revisions' => QuoteRevisionResource::collection($quote->revisions()->with('editor')->latest('id')->get()),
        ]);
    }

    public function update(UpdateQuoteRequest $request, Quote $quote, EditQuote $editQuote): RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $editQuote->handle($quote, $request->content(), $user->discordUser);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quote updated.')]);

        // Imported quotes are edited from the review queue; they have no public page yet.
        return $quote->status === QuoteStatus::Published ? to_route('quotes.show', $quote) : back();
    }

    public function destroy(Quote $quote, RemoveQuote $removeQuote): RedirectResponse
    {
        Gate::authorize('delete', $quote);

        $removeQuote->handle($quote);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quote removed.')]);

        return to_route('quotes.index');
    }

    public function restore(Quote $quote, RestoreQuote $restoreQuote): RedirectResponse
    {
        Gate::authorize('restore', $quote);

        $restoreQuote->handle($quote);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quote restored.')]);

        return to_route('quotes.show', $quote);
    }

    public function random(Request $request): RedirectResponse
    {
        $quote = Quote::query()->visibleTo($this->access($request))->inRandomOrder()->first();

        return $quote !== null
            ? to_route('quotes.show', $quote)
            : to_route('quotes.index');
    }

    /**
     * Relations QuoteResource needs, with the user's own vote.
     *
     * @return array<int|string, string|Closure(Relation<*, *, *>): mixed>
     */
    public static function cardRelations(AccessSnapshot $access): array
    {
        return [
            'lines.person',
            'guild',
            'submittedBy',
            'myVote' => fn (Relation $vote): Relation => $vote->where('user_id', $access->userId),
        ];
    }

    private function access(Request $request): AccessSnapshot
    {
        return AccessSnapshot::forRequest($request) ?? throw new LogicException('Quote pages require a logged-in user.');
    }
}
