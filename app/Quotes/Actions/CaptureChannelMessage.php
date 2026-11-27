<?php

declare(strict_types=1);

namespace App\Quotes\Actions;

use App\Access\Actions\RecordDiscordUser;
use App\Ai\CaptureInput;
use App\Ai\QuoteDetector;
use App\Ai\QuoteNormalizer;
use App\Discord\Data\DiscordUserData;
use App\Enums\QuoteSource;
use App\Enums\QuoteStatus;
use App\Enums\SaidAtPrecision;
use App\Models\Guild;
use App\Models\Quote;
use App\Models\QuoteImport;
use App\Quotes\Data\NormalizedQuote;
use App\Quotes\Data\SourceMessage;
use App\Quotes\Discord\QuoteInteractions;
use App\Quotes\Exceptions\AlreadyQuotedException;
use Carbon\CarbonImmutable;

/**
 * Turns one Discord message from a channel into a `pending_review` quote
 * if it looks like one (`QuoteDetector`), for channel imports and watched
 * channels. Skips bots, empty messages and messages already quoted.
 */
final readonly class CaptureChannelMessage
{
    public function __construct(
        private QuoteDetector $detector,
        private QuoteNormalizer $normalizer,
        private DraftQuote $draftQuote,
        private RecordDiscordUser $recordUser,
    ) {}

    /**
     * @param  array<mixed>  $message  Discord message object
     */
    public function handle(QuoteImport $import, Guild $guild, array $message, QuoteSource $source): ?Quote
    {
        $content = is_string($message['content'] ?? null) ? trim($message['content']) : '';
        $author = is_array($message['author'] ?? null) ? DiscordUserData::fromPayload($message['author']) : null;
        $messageId = is_string($message['id'] ?? null) ? $message['id'] : null;

        if ($content === '' || $messageId === null || ! $author instanceof DiscordUserData || ! $author->isValid() || ($message['author']['bot'] ?? false) === true) {
            return null;
        }

        if (Quote::query()->where('source_message_id', $messageId)->exists() || ! $this->detector->isQuote($content)) {
            return null;
        }

        $postedAt = is_string($message['timestamp'] ?? null) ? CarbonImmutable::parse($message['timestamp']) : null;
        $normalized = $this->normalizer->normalize(new CaptureInput(
            text: $content,
            mentionNames: QuoteInteractions::mentionNames($message['mentions'] ?? null),
            now: $postedAt,
        ));

        // Without a stated time, the quote was said around when it was posted.
        if (! $normalized->saidAt instanceof CarbonImmutable && $postedAt instanceof CarbonImmutable) {
            $normalized = new NormalizedQuote($normalized->lines, $normalized->context, $postedAt->startOfDay(), SaidAtPrecision::Day, $normalized->aiOutput);
        }

        try {
            $quote = $this->draftQuote->handle(
                $guild,
                $this->recordUser->handle($author),
                $source,
                $content,
                $normalized,
                new SourceMessage($messageId, $import->channel_id, "https://discord.com/channels/{$guild->discord_id}/{$import->channel_id}/{$messageId}"),
                QuoteStatus::PendingReview,
            );
        } catch (AlreadyQuotedException) {
            return null;
        }

        $quote->update(['import_id' => $import->id]);
        $import->increment('quotes_found');

        return $quote;
    }
}
