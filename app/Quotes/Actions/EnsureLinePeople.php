<?php

declare(strict_types=1);

namespace App\Quotes\Actions;

use App\Models\Person;
use App\Models\Quote;
use App\Models\QuoteLine;

/**
 * Gives every line of a quote a Person: the Discord user's person, an
 * existing person with exactly that name, or a new one. Used when a quote is
 * saved and whenever a saved quote is edited.
 */
final readonly class EnsureLinePeople
{
    public function handle(Quote $quote): void
    {
        foreach ($quote->lines as $line) {
            if ($line->person_id === null) {
                $line->update(['person_id' => $this->personFor($line)->id]);
            }
        }

        $quote->unsetRelation('lines');
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
