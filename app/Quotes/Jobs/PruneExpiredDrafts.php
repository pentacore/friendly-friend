<?php

declare(strict_types=1);

namespace App\Quotes\Jobs;

use App\Access\AppSettings;
use App\Discord\DiscordClient;
use App\Enums\AppSetting;
use App\Enums\QuoteStatus;
use App\Models\Quote;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Deletes drafts nobody saved within the draft lifetime (`AppSetting::DraftTtlMinutes`),
 * after turning their private preview into "expired" (no buttons): the
 * interaction reply while its token still works, or the DM.
 */
#[UniqueFor(120)]
final class PruneExpiredDrafts implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const string EXPIRED = 'This draft expired before it was saved. Start again to save the quote.';

    public function handle(DiscordClient $discord, AppSettings $settings): void
    {
        $expired = Quote::query()
            ->where('status', QuoteStatus::Draft)
            ->where('updated_at', '<', now()->subMinutes($settings->get(AppSetting::DraftTtlMinutes)))
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
