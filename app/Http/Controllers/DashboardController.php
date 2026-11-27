<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Access\AccessSnapshot;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
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
        ]);
    }
}
