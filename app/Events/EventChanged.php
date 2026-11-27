<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells everyone viewing an event that one part of it changed, so their
 * page reloads that part (Reverb, `events.{id}`). Broadcast-only.
 */
final readonly class EventChanged implements ShouldBroadcast
{
    use Dispatchable;

    public const string OVERVIEW = 'overview';

    public const string FOOD = 'food';

    public const string TRANSPORT = 'transport';

    public const string COSTS = 'costs';

    /**
     * @param  self::OVERVIEW|self::FOOD|self::TRANSPORT|self::COSTS  $section
     */
    public function __construct(
        public int $eventId,
        public string $section,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("events.{$this->eventId}")];
    }

    public function broadcastAs(): string
    {
        return 'event.changed';
    }

    /**
     * @return array{event_id: int, section: 'overview'|'food'|'transport'|'costs'}
     */
    public function broadcastWith(): array
    {
        return ['event_id' => $this->eventId, 'section' => $this->section];
    }
}
