<?php

declare(strict_types=1);

namespace App\Quotes\Actions;

use App\Enums\QuoteStatus;
use App\Models\DiscordUser;
use App\Models\Person;
use App\Models\Quote;
use App\Models\QuoteLine;
use Illuminate\Support\Facades\DB;

/**
 * Saves a draft (or approves an imported quote): every speaker becomes a
 * Person, the quote is published and the first revision is recorded.
 */
final readonly class ConfirmQuote
{
    public function __construct(private RecordRevision $recordRevision) {}

    public function handle(Quote $quote, DiscordUser $editor): Quote
    {
        return DB::transaction(function () use ($quote, $editor): Quote {
            foreach ($quote->lines as $line) {
                if ($line->person_id === null) {
                    $line->update(['person_id' => $this->personFor($line)->id]);
                }
            }

            $quote->update([
                'status' => QuoteStatus::Published,
                'published_at' => now(),
            ]);

            $quote->unsetRelation('lines');
            $this->recordRevision->handle($quote, $editor);

            return $quote;
        });
    }

    private function personFor(QuoteLine $line): Person
    {
        if ($line->speaker_discord_user_id !== null) {
            return Person::query()->where('discord_user_id', $line->speaker_discord_user_id)->first()
                ?? Person::createNamed($line->speaker_name, $line->speaker_discord_user_id);
        }

        return Person::query()->whereRaw('lower(display_name) = ?', [mb_strtolower($line->speaker_name)])->first()
            ?? Person::createNamed($line->speaker_name);
    }
}
