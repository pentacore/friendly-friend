<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Event;
use App\Models\Person;
use App\Models\Quote;
use App\Models\QuoteLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Override;

/**
 * What the header search found: a few quotes (first line, shortened),
 * people and events, and whether the quotes are only close spellings.
 */
final class SearchResultsResource extends JsonResource
{
    /** Quote text is cut to this many characters in the results. */
    private const int TEXT_LENGTH = 140;

    /**
     * @param  Collection<int, Quote>  $quotes
     * @param  Collection<int, Person>  $people
     * @param  Collection<int, Event>  $events
     */
    public function __construct(
        private readonly Collection $quotes,
        private readonly Collection $people,
        private readonly Collection $events,
        private readonly bool $closeMatches,
    ) {
        parent::__construct(null);
    }

    /**
     * @return array{
     *     quotes: list<array{id: int, speaker: string, text: string}>,
     *     people: list<array{slug: string, display_name: string}>,
     *     events: list<array{id: int, title: string, starts_at: string}>,
     *     close_matches: bool,
     * }
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'quotes' => array_values($this->quotes->map(function (Quote $quote): array {
                $line = $quote->lines->sortBy('position')->first();

                return [
                    'id' => $quote->id,
                    'speaker' => $line instanceof QuoteLine ? $line->speaker_name : '',
                    'text' => $line instanceof QuoteLine ? mb_strimwidth($line->text, 0, self::TEXT_LENGTH, '…') : '',
                ];
            })->all()),
            'people' => array_values($this->people->map(fn (Person $person): array => ['slug' => $person->slug, 'display_name' => $person->display_name])->all()),
            'events' => array_values($this->events->map(fn (Event $event): array => ['id' => $event->id, 'title' => $event->title, 'starts_at' => $event->starts_at->toIso8601String()])->all()),
            'close_matches' => $this->closeMatches,
        ];
    }
}
