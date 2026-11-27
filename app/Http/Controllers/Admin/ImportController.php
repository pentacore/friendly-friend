<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Access\AccessSnapshot;
use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\QuoteController;
use App\Http\Resources\QuoteImportResource;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use App\Models\QuoteImport;
use App\Models\User;
use App\Quotes\Actions\ConfirmQuote;
use App\Quotes\Actions\RemoveQuote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use Pentacore\Typefinder\Attributes\TypefinderPage;

/**
 * Admins review quotes found by channel imports (/quote import).
 */
final class ImportController extends Controller
{
    #[TypefinderPage(component: 'admin/Imports', props: ['imports' => QuoteImportResource::class.'[]'])]
    public function index(Request $request): Response
    {
        $access = $this->access($request);

        return Inertia::render('admin/Imports', [
            'imports' => QuoteImportResource::collection(
                QuoteImport::query()
                    ->whereIn('guild_id', $access->adminGuildIds)
                    ->with('guild')
                    ->withCount(['quotes as pending_count' => fn (Builder $quotes) => $quotes->where('status', QuoteStatus::PendingReview)])
                    ->latest('id')
                    ->get(),
            ),
        ]);
    }

    #[TypefinderPage(component: 'admin/ImportReview', props: [
        'quote_import' => QuoteImportResource::class,
        'pending' => QuoteResource::class.'[]',
    ])]
    public function show(Request $request, QuoteImport $import): Response
    {
        $access = $this->moderator($request, $import);
        $import->load('guild')->loadCount(['quotes as pending_count' => fn (Builder $quotes) => $quotes->where('status', QuoteStatus::PendingReview)]);

        return Inertia::render('admin/ImportReview', [
            'quote_import' => new QuoteImportResource($import),
            'pending' => QuoteResource::collection(
                $import->quotes()
                    ->where('status', QuoteStatus::PendingReview)
                    ->with(QuoteController::cardRelations($access))
                    ->orderBy('said_at')
                    ->orderBy('id')
                    ->get(),
            ),
        ]);
    }

    public function approve(Request $request, QuoteImport $import, Quote $quote, ConfirmQuote $confirmQuote): RedirectResponse
    {
        $this->moderator($request, $import);
        $this->pendingFrom($import, $quote);

        $confirmQuote->handle($quote, $this->user($request)->discordUser);

        return back();
    }

    public function reject(Request $request, QuoteImport $import, Quote $quote, RemoveQuote $removeQuote): RedirectResponse
    {
        $this->moderator($request, $import);
        $this->pendingFrom($import, $quote);

        $removeQuote->handle($quote);

        return back();
    }

    public function approveAll(Request $request, QuoteImport $import, ConfirmQuote $confirmQuote): RedirectResponse
    {
        $this->moderator($request, $import);
        $editor = $this->user($request)->discordUser;

        $import->quotes()->where('status', QuoteStatus::PendingReview)->with('lines')->get()
            ->each(fn (Quote $quote): Quote => $confirmQuote->handle($quote, $editor));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('All quotes approved.')]);

        return back();
    }

    private function moderator(Request $request, QuoteImport $import): AccessSnapshot
    {
        $access = $this->access($request);

        abort_unless($access->moderates($import->guild_id), 403);

        return $access;
    }

    private function pendingFrom(QuoteImport $import, Quote $quote): void
    {
        abort_unless($quote->import_id === $import->id && $quote->status === QuoteStatus::PendingReview, 404);
    }

    private function access(Request $request): AccessSnapshot
    {
        return AccessSnapshot::forRequest($request) ?? throw new LogicException('Imports require a logged-in user.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new LogicException('Imports require a logged-in user.');
    }
}
