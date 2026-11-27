<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Quotes\VoteRequest;
use App\Http\Resources\VoteResultResource;
use App\Models\Quote;
use App\Models\User;
use App\Quotes\Actions\RecordVote;
use LogicException;

/**
 * Voting on a quote (JSON, called with Inertia's useHttp).
 */
final class QuoteVoteController extends Controller
{
    public function store(VoteRequest $request, Quote $quote, RecordVote $recordVote): VoteResultResource
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('Voting requires a logged-in user.');
        }

        $myVote = $recordVote->handle($quote, $user, $request->integer('value'));

        return new VoteResultResource($quote, $myVote);
    }
}
