<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Access\AccessSnapshot;
use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\QuoteController;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use Pentacore\Typefinder\Attributes\TypefinderPage;

/**
 * Admins see the quotes they've removed (servers they moderate) and can
 * put them back.
 */
final class RemovedQuoteController extends Controller
{
    #[TypefinderPage(component: 'admin/RemovedQuotes', props: [
        'quotes' => 'SimplePaginatedResourceCollection<'.QuoteResource::class.'>',
    ])]
    public function index(Request $request): Response
    {
        $access = AccessSnapshot::forRequest($request) ?? throw new LogicException('Admin pages require a logged-in user.');

        $quotes = Quote::query()
            ->where('status', QuoteStatus::Removed)
            ->whereIn('guild_id', $access->adminGuildIds)
            ->with(QuoteController::cardRelations($access))
            ->latest('updated_at')
            ->simplePaginate(20);

        return Inertia::render('admin/RemovedQuotes', [
            'quotes' => Inertia::scroll(QuoteResource::collection($quotes)),
        ]);
    }
}
