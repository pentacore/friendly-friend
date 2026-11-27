<?php

declare(strict_types=1);

namespace App\Quotes\Actions;

use App\Models\DiscordUser;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteRevision;

/**
 * Snapshots a quote's current content. Called on every save and edit.
 */
final readonly class RecordRevision
{
    public function handle(Quote $quote, DiscordUser $editor): QuoteRevision
    {
        return $quote->revisions()->create([
            'editor_discord_user_id' => $editor->id,
            'snapshot' => [
                'lines' => $quote->lines->map(fn (QuoteLine $line): array => [
                    'speaker' => $line->speaker_name,
                    'person_id' => $line->person_id,
                    'text' => $line->text,
                ])->values()->all(),
                'context' => $quote->context,
                'said_at' => $quote->said_at?->toIso8601String(),
            ],
        ]);
    }
}
