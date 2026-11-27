<?php

declare(strict_types=1);

namespace App\Quotes\Jobs;

use App\Discord\DiscordClient;
use App\Enums\QuoteSource;
use App\Models\Guild;
use App\Models\Quote;
use App\Models\QuoteImport;
use App\Quotes\Actions\CaptureChannelMessage;
use App\Quotes\Actions\ConfirmQuote;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;

/**
 * A message in a watched channel: if it looks like a quote, it waits in
 * that channel's standing import for an admin (or is published straight
 * away when the server opted in), and the bot reacts 📝 so people can see
 * it was noticed.
 */
#[Tries(3)]
final class PickUpPostedQuote implements ShouldQueue
{
    use Queueable;

    public const string NOTICED = '📝';

    /**
     * @param  array<mixed>  $message  MESSAGE_CREATE
     */
    public function __construct(public readonly array $message) {}

    public function handle(CaptureChannelMessage $capture, ConfirmQuote $confirm, DiscordClient $discord): void
    {
        $guildId = is_string($this->message['guild_id'] ?? null) ? $this->message['guild_id'] : '';
        $channelId = is_string($this->message['channel_id'] ?? null) ? $this->message['channel_id'] : '';
        $guild = Guild::query()->enabled()->where('discord_id', $guildId)->first();

        if (! $guild instanceof Guild || ! in_array($channelId, $guild->watched_channel_ids, true)) {
            return;
        }

        $import = QuoteImport::query()
            ->where('guild_id', $guild->id)
            ->where('channel_id', $channelId)
            ->where('status', QuoteImport::WATCHING)
            ->first();

        if (! $import instanceof QuoteImport) {
            return;
        }

        $import->increment('messages_scanned');
        $quote = $capture->handle($import, $guild, $this->message, QuoteSource::WatchedChannel);

        if ($quote instanceof Quote && $guild->publish_watched_quotes) {
            $confirm->handle($quote, $quote->submittedBy);
        }

        $messageId = is_string($this->message['id'] ?? null) ? $this->message['id'] : null;

        if (! $quote instanceof Quote || $messageId === null || ! $discord->configured()) {
            return;
        }

        try {
            $discord->addReaction($channelId, $messageId, self::NOTICED);
        } catch (RequestException $e) {
            // Missing "Add Reactions" isn't worth failing over: the quote is already queued.
            Log::warning("Couldn't react to a picked-up quote.", ['status' => $e->response->status()]);
        }
    }
}
