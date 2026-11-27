<?php

declare(strict_types=1);

namespace App\Quotes\Actions;

use App\Models\Quote;
use App\Quotes\Data\NormalizedQuote;

/**
 * Replaces a quote's lines, context and time with normalized content,
 * resolving each speaker.
 */
final readonly class WriteDraftLines
{
    public function __construct(private ResolvePerson $resolvePerson) {}

    public function handle(Quote $quote, NormalizedQuote $content): void
    {
        $quote->update([
            'context' => $content->context,
            'said_at' => $content->saidAt,
            'said_at_precision' => $content->saidAtPrecision,
            'ai_output' => $content->aiOutput ?? $quote->ai_output,
        ]);

        $quote->lines()->delete();

        foreach ($content->lines as $position => $line) {
            $speaker = $this->resolvePerson->handle($quote->guild, $line);

            $quote->lines()->create([
                'position' => $position,
                'person_id' => $speaker->personId,
                'speaker_discord_user_id' => $speaker->discordUserId,
                'speaker_name' => $speaker->name,
                'text' => trim($line->text),
            ]);
        }

        $quote->unsetRelation('lines');
    }
}
