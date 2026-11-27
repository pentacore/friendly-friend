<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Access\AccessSnapshot;
use App\Enums\QuoteStatus;
use App\Enums\SaidAtPrecision;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Quotes\QuoteRules;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * A quote as the website shows it. Load `lines.person`, `guild`,
 * `submittedBy` and a constrained `myVote` to avoid N+1 queries.
 */
final class QuoteResource extends JsonResource
{
    public function __construct(private readonly Quote $quote)
    {
        parent::__construct($quote);
    }

    /**
     * @return array{
     *     id: int,
     *     guild: array{id: int, name: string, icon_url: string|null},
     *     lines: list<array{speaker_name: string, text: string, person: array{id: int, slug: string, display_name: string}|null}>,
     *     context: string|null,
     *     said_at: string|null,
     *     said_at_precision: SaidAtPrecision|null,
     *     status: QuoteStatus,
     *     score: int,
     *     my_vote: int,
     *     elo: int,
     *     submitted_by: array{name: string, avatar_url: string},
     *     published_at: string|null,
     *     source_message_url: string|null,
     *     can: array{update: bool, delete: bool},
     * }
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $quote = $this->quote;
        $access = AccessSnapshot::forRequest($request);

        return [
            'id' => $quote->id,
            'guild' => [
                'id' => $quote->guild->id,
                'name' => $quote->guild->name,
                'icon_url' => $quote->guild->iconUrl(),
            ],
            'lines' => array_values($quote->lines->map(fn (QuoteLine $line): array => [
                'speaker_name' => $line->speaker_name,
                'text' => $line->text,
                'person' => $line->person !== null
                    ? ['id' => $line->person->id, 'slug' => $line->person->slug, 'display_name' => $line->person->display_name]
                    : null,
            ])->all()),
            'context' => $quote->context,
            'said_at' => $quote->said_at?->toIso8601String(),
            'said_at_precision' => $quote->said_at_precision,
            'status' => $quote->status,
            'score' => $quote->score,
            'my_vote' => $quote->relationLoaded('myVote') ? ($quote->myVote->value ?? 0) : 0,
            'elo' => $quote->elo,
            'submitted_by' => [
                'name' => $quote->submittedBy->display_name,
                'avatar_url' => $quote->submittedBy->avatarUrl(),
            ],
            'published_at' => $quote->published_at?->toIso8601String(),
            'source_message_url' => $quote->source_message_url,
            'can' => [
                'update' => $access instanceof AccessSnapshot && QuoteRules::canUpdate($access, $quote),
                'delete' => $access instanceof AccessSnapshot && QuoteRules::canDelete($access, $quote),
            ],
        ];
    }
}
