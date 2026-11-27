<?php

declare(strict_types=1);

namespace App\Planning\Data;

use Carbon\CarbonImmutable;

/**
 * What the event form edits.
 */
final readonly class EventDetails
{
    /**
     * @param  list<int>  $hostIds  discord_users.id of co-hosts
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public ?string $location,
        public ?string $locationUrl,
        public CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt,
        public ?CarbonImmutable $rsvpBy,
        public string $timezone,
        public array $hostIds,
    ) {}
}
