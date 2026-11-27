<?php

declare(strict_types=1);

namespace App\Quotes\Jobs;

use App\Discord\DiscordClient;
use App\Enums\QuoteStatus;
use App\Models\Quote;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Deletes drafts nobody saved within config('quotes.draft_ttl_minutes'),
 * after turning their private preview into "expired" (no buttons): the
 * interaction reply while its token still works, or the DM.
 */
final class PruneExpiredDrafts implements ShouldQueue
{
    use Queueable;

    public const string EXPIRED = 'This draft expired before it was saved. Start again to save the quote.';

    public function handle(DiscordClient $discord): void
    {
        $expired = Quote::query()
            ->where('status', QuoteStatus::Draft)
            ->where('updated_at', '<', now()->subMinutes(config()->integer('quotes.draft_ttl_minutes')))
            ->get();

        foreach ($expired as $draft) {
            $this->markPreviewExpired($discord, $draft);
            $draft->delete();
        }
    }

    private function markPreviewExpired(DiscordClient $discord, Quote $draft): void
    {
        if (! $discord->configured()) {
            return;
        }

        $message = ['content' => self::EXPIRED, 'components' => []];

        try {
            if ($draft->preview_token !== null) {
                $discord->editInteractionResponse($draft->preview_token, $message);
            } elseif ($draft->preview_channel_id !== null && $draft->preview_message_id !== null) {
                $discord->editMessage($draft->preview_channel_id, $draft->preview_message_id, $message);
            }
        } catch (Throwable $e) {
            // The token may just have run out; the buttons still answer "expired".
            Log::info("Couldn't mark the preview of draft {$draft->id} as expired.", ['exception' => $e->getMessage()]);
        }
    }
}
