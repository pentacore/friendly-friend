<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Pushes a quote's new score to everyone viewing that server's quotes
 * (Reverb, `guilds.{id}.quotes`). Broadcast-only: nothing listens to it on
 * the server.
 */
final readonly class QuoteScoreChanged implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(
        public int $quoteId,
        public int $guildId,
        public int $score,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("guilds.{$this->guildId}.quotes")];
    }

    public function broadcastAs(): string
    {
        return 'quote.score-changed';
    }

    /**
     * @return array{quote_id: int, score: int}
     */
    public function broadcastWith(): array
    {
        return ['quote_id' => $this->quoteId, 'score' => $this->score];
    }
}
