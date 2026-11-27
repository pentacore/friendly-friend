<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Access\AccessSnapshot;
use App\Http\Requests\Quotes\MatchupRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use App\Models\User;
use App\Quotes\Actions\RecordMatchup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use Pentacore\Typefinder\Attributes\TypefinderPage;

/**
 * This or that: pick the funnier of two quotes; picks feed the Elo ranking.
 */
final class ThisOrThatController extends Controller
{
    /** Pairs favour quotes that have been played least; the jitter keeps it from being predictable. */
    private const int JITTER = 3;

    #[TypefinderPage(component: 'quotes/ThisOrThat', props: ['pair' => QuoteResource::class.'[] | null'])]
    public function show(Request $request): Response
    {
        $access = AccessSnapshot::forRequest($request) ?? throw new LogicException('This or that requires a logged-in user.');

        $pair = Quote::query()
            ->visibleTo($access)
            ->with(QuoteController::cardRelations($access))
            ->orderByRaw('matchup_count + random() * ?', [self::JITTER])
            ->limit(2)
            ->get();

        return Inertia::render('quotes/ThisOrThat', [
            'pair' => $pair->count() === 2 ? QuoteResource::collection($pair) : null,
        ]);
    }

    public function store(MatchupRequest $request, RecordMatchup $recordMatchup): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('This or that requires a logged-in user.');
        }

        $recordMatchup->handle(
            $user,
            Quote::query()->findOrFail($request->integer('quote_a')),
            Quote::query()->findOrFail($request->integer('quote_b')),
            Quote::query()->findOrFail($request->integer('winner')),
        );

        return to_route('this-or-that.show');
    }
}
