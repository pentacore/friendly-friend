<?php

declare(strict_types=1);

namespace App\Quotes\Jobs;

use App\Access\Actions\RecordDiscordUser;
use App\Ai\CaptureInput;
use App\Ai\QuoteDetector;
use App\Ai\QuoteNormalizer;
use App\Discord\Data\DiscordUserData;
use App\Discord\DiscordClient;
use App\Enums\QuoteSource;
use App\Enums\QuoteStatus;
use App\Enums\SaidAtPrecision;
use App\Models\Quote;
use App\Models\QuoteImport;
use App\Quotes\Actions\DraftQuote;
use App\Quotes\Data\NormalizedQuote;
use App\Quotes\Data\SourceMessage;
use App\Quotes\Discord\QuoteInteractions;
use App\Quotes\Exceptions\AlreadyQuotedException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Throwable;

/**
 * Walks a channel's history backwards, a few pages per run, and turns
 * messages that look like quotes into `pending_review` quotes for an admin
 * to approve on the website. Resumable: the cursor is the oldest message
 * seen, and already-imported messages are skipped (unique source message).
 */
#[Timeout(600)]
#[Tries(3)]
final class ImportChannelQuotes implements ShouldQueue
{
    use Queueable;

    private const int PAGES_PER_RUN = 5;

    private const int PAGE_SIZE = 100;

    public function __construct(public readonly int $importId) {}

    public function handle(
        DiscordClient $discord,
        QuoteDetector $detector,
        QuoteNormalizer $normalizer,
        DraftQuote $draftQuote,
        RecordDiscordUser $recordUser,
    ): void {
        $import = QuoteImport::query()->with('guild')->find($this->importId);

        if ($import === null || in_array($import->status, ['done', 'failed'], true)) {
            return;
        }

        $import->update(['status' => 'running', 'started_at' => $import->started_at ?? now()]);

        try {
            for ($page = 0; $page < self::PAGES_PER_RUN; $page++) {
                $messages = $discord->channelMessages($import->channel_id, $import->before_message_id, self::PAGE_SIZE);

                foreach ($messages as $message) {
                    $this->consider($import, $message, $detector, $normalizer, $draftQuote, $recordUser);
                }

                $oldest = end($messages);
                $import->update([
                    'messages_scanned' => $import->messages_scanned + count($messages),
                    'before_message_id' => is_array($oldest) && is_string($oldest['id'] ?? null) ? $oldest['id'] : $import->before_message_id,
                ]);

                if (count($messages) < self::PAGE_SIZE) {
                    $import->update(['status' => 'done', 'finished_at' => now()]);

                    return;
                }
            }
        } catch (Throwable $e) {
            $import->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => now()]);

            throw $e;
        }

        // More history left: continue in a fresh job.
        dispatch(new self($import->id));
    }

    /**
     * @param  array<mixed>  $message
     */
    private function consider(QuoteImport $import, array $message, QuoteDetector $detector, QuoteNormalizer $normalizer, DraftQuote $draftQuote, RecordDiscordUser $recordUser): void
    {
        $content = is_string($message['content'] ?? null) ? trim($message['content']) : '';
        $author = is_array($message['author'] ?? null) ? DiscordUserData::fromPayload($message['author']) : null;
        $messageId = is_string($message['id'] ?? null) ? $message['id'] : null;

        if ($content === '' || $messageId === null || ! $author instanceof DiscordUserData || ! $author->isValid() || ($message['author']['bot'] ?? false) === true) {
            return;
        }

        if (Quote::query()->where('source_message_id', $messageId)->exists() || ! $detector->isQuote($content)) {
            return;
        }

        $postedAt = is_string($message['timestamp'] ?? null) ? CarbonImmutable::parse($message['timestamp']) : null;
        $normalized = $normalizer->normalize(new CaptureInput(
            text: $content,
            mentionNames: QuoteInteractions::mentionNames($message['mentions'] ?? null),
            now: $postedAt,
        ));

        // Without a stated time, the quote was said around when it was posted.
        if (! $normalized->saidAt instanceof CarbonImmutable && $postedAt instanceof CarbonImmutable) {
            $normalized = new NormalizedQuote($normalized->lines, $normalized->context, $postedAt->startOfDay(), SaidAtPrecision::Day, $normalized->aiOutput);
        }

        try {
            $quote = $draftQuote->handle(
                $import->guild,
                $recordUser->handle($author),
                QuoteSource::ChannelImport,
                $content,
                $normalized,
                new SourceMessage($messageId, $import->channel_id, "https://discord.com/channels/{$import->guild->discord_id}/{$import->channel_id}/{$messageId}"),
                QuoteStatus::PendingReview,
            );
        } catch (AlreadyQuotedException) {
            return;
        }

        $quote->update(['import_id' => $import->id]);
        $import->increment('quotes_found');
    }
}
