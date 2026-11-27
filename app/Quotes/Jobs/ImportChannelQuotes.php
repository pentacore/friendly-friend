<?php

declare(strict_types=1);

namespace App\Quotes\Jobs;

use App\Discord\DiscordClient;
use App\Enums\QuoteSource;
use App\Models\QuoteImport;
use App\Quotes\Actions\CaptureChannelMessage;
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

    public function handle(DiscordClient $discord, CaptureChannelMessage $capture): void
    {
        $import = QuoteImport::query()->with('guild')->find($this->importId);

        if ($import === null || in_array($import->status, ['done', 'failed'], true)) {
            return;
        }

        $import->update(['status' => 'running', 'started_at' => $import->started_at ?? now()]);

        try {
            for ($page = 0; $page < self::PAGES_PER_RUN; $page++) {
                $messages = $discord->channelMessages($import->channel_id, $import->before_message_id, self::PAGE_SIZE);

                foreach ($messages as $message) {
                    $capture->handle($import, $import->guild, $message, QuoteSource::ChannelImport);
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
}
