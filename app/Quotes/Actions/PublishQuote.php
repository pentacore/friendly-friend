<?php

declare(strict_types=1);

namespace App\Quotes\Actions;

use App\Discord\DiscordClient;
use App\Models\Quote;
use App\Quotes\Discord\QuoteMessages;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Posts a saved quote to its guild's quotes channel, if one is set. A
 * failure to post (missing permission, deleted channel) never undoes the save.
 */
final readonly class PublishQuote
{
    public function __construct(private DiscordClient $discord) {}

    public function handle(Quote $quote): void
    {
        $channelId = $quote->guild->quotes_channel_id;

        if ($channelId === null) {
            return;
        }

        $footer = '— saved by '.$quote->submittedBy->display_name
            .($quote->source_message_url !== null ? " · <{$quote->source_message_url}>" : '');

        try {
            $this->discord->sendMessage($channelId, QuoteMessages::published($quote, $footer));
        } catch (Throwable $e) {
            Log::warning('Could not post a saved quote to the quotes channel.', ['quote' => $quote->id, 'exception' => $e->getMessage()]);
        }
    }
}
