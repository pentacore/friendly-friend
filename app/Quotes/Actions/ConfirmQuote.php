<?php

declare(strict_types=1);

namespace App\Quotes\Actions;

use App\Enums\QuoteStatus;
use App\Models\DiscordUser;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;

/**
 * Saves a draft (or approves an imported quote): every speaker becomes a
 * Person, the quote is published and the first revision is recorded.
 */
final readonly class ConfirmQuote
{
    public function __construct(
        private EnsureLinePeople $ensurePeople,
        private RecordRevision $recordRevision,
    ) {}

    public function handle(Quote $quote, DiscordUser $editor): Quote
    {
        return DB::transaction(function () use ($quote, $editor): Quote {
            $this->ensurePeople->handle($quote);

            $quote->update([
                'status' => QuoteStatus::Published,
                'published_at' => now(),
                // Only drafts need to know where their preview is.
                'preview_token' => null,
                'preview_channel_id' => null,
                'preview_message_id' => null,
            ]);

            $this->recordRevision->handle($quote, $editor);

            return $quote;
        });
    }
}
