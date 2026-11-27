<?php

declare(strict_types=1);

namespace App\Quotes\Data;

/**
 * Who a quote line's speaker turned out to be: an existing person, a Discord
 * user who gets a person on save, or (neither) a new person by name.
 */
final readonly class ResolvedSpeaker
{
    public function __construct(
        public string $name,
        public ?int $personId = null,
        public ?int $discordUserId = null,
    ) {}

    public function isNew(): bool
    {
        return $this->personId === null && $this->discordUserId === null;
    }
}
