<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Pentacore\Typefinder\Attributes\TypefinderBroadcast;

/**
 * Tells everyone viewing an event that one part of it changed, so their
 * page reloads that part (Reverb, `events.{id}`). Broadcast-only.
 */
#[TypefinderBroadcast(payload: ['event_id' => 'number', 'section' => "'overview' | 'food' | 'transport' | 'costs'"])]
final readonly class EventChanged implements ShouldBroadcast
{
    use Dispatchable;

    public const string OVERVIEW = 'overview';

    public const string FOOD = 'food';

    public const string TRANSPORT = 'transport';

    public const string COSTS = 'costs';

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
     * @return array{event_id: int, section: string}
     */
    public function broadcastWith(): array
    {
        return ['event_id' => $this->eventId, 'section' => $this->section];
    }
}
