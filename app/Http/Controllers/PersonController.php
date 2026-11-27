<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Access\AccessSnapshot;
use App\Http\Resources\QuoteResource;
use App\Models\Person;
use App\Models\PersonAlias;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use Pentacore\Typefinder\Attributes\TypefinderPage;

/**
 * A person's page: who they are and their visible quotes.
 */
final class PersonController extends Controller
{
    #[TypefinderPage(component: 'people/Show', props: [
        'person' => '{ id: number; slug: string; display_name: string; aliases: string[]; avatar_url: string | null; quote_count: number }',
        'quotes' => 'SimplePaginatedResourceCollection<'.QuoteResource::class.'>',
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
        ]);
    }
}
