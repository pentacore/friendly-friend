<?php

declare(strict_types=1);

namespace App\Planning\Data;

use App\Enums\Currency;
use Carbon\CarbonImmutable;

/**
 * What the event form edits.
 */
final readonly class EventDetails
{
    /**
     * @param  list<int>  $hostIds  discord_users.id of co-hosts
     * @param  list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable|null}>  $dateOptions  two or more: let people vote on the date
     * @param  Currency|null  $currency  null keeps the current one (kronor for a new event)
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
        public array $dateOptions = [],
        public ?Currency $currency = null,
    ) {}
}
