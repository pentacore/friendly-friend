<?php

declare(strict_types=1);

namespace App\Quotes\Actions;

use App\Enums\QuoteSource;
use App\Enums\QuoteStatus;
use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\Quote;
use App\Quotes\Data\NormalizedQuote;
use App\Quotes\Data\SourceMessage;
use App\Quotes\Exceptions\AlreadyQuotedException;
use Illuminate\Support\Facades\DB;

/**
 * Starts a quote as a private draft. A message can only be quoted once:
 * an unsaved draft of it is replaced, a saved quote of it is refused.
 */
final readonly class DraftQuote
{
    public function __construct(private WriteDraftLines $writeLines) {}

    /**
     * @throws AlreadyQuotedException
     */
    public function handle(
        Guild $guild,
        DiscordUser $submitter,
        QuoteSource $source,
        string $rawInput,
        NormalizedQuote $content,
        ?SourceMessage $sourceMessage = null,
        QuoteStatus $status = QuoteStatus::Draft,
    ): Quote {
        return DB::transaction(function () use ($guild, $submitter, $source, $rawInput, $content, $sourceMessage, $status): Quote {
            if ($sourceMessage instanceof SourceMessage) {
                $this->releaseSourceMessage($sourceMessage->messageId);
            }

            $quote = Quote::query()->create([
                'guild_id' => $guild->id,
                'status' => $status,
                'source' => $source,
                'source_message_id' => $sourceMessage?->messageId,
                'source_channel_id' => $sourceMessage?->channelId,
                'source_message_url' => $sourceMessage?->url,
                'submitted_by_discord_user_id' => $submitter->id,
                'raw_input' => $rawInput,
            ]);

            $quote->setRelation('guild', $guild);
            $this->writeLines->handle($quote, $content);

            return $quote;
        });
    }

    private function releaseSourceMessage(string $messageId): void
    {
        $existing = Quote::query()->where('source_message_id', $messageId)->first();

        if ($existing === null) {
            return;
        }

        if ($existing->status !== QuoteStatus::Draft) {
            throw new AlreadyQuotedException($existing);
        }

        $existing->delete();
    }
}
