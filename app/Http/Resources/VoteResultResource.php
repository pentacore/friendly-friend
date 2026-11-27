<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * The answer to a vote: the quote's new score and the voter's own vote.
 */
final class VoteResultResource extends JsonResource
{
    public function __construct(private readonly Quote $quote, private readonly int $myVote)
    {
        parent::__construct($quote);
    }

    /**
     * @return array{quote_id: int, score: int, my_vote: int}
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return ['quote_id' => $this->quote->id, 'score' => $this->quote->score, 'my_vote' => $this->myVote];
    }
}
