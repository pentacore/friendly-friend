<?php

declare(strict_types=1);

namespace App\Stats\Jobs;

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Stats\Actions\RecordActivities;
use App\Stats\Tracking;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;

/**
 * A member's presence changed: what they play now (possibly nothing).
 */
#[Tries(3)]
final class IngestPresence implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, int>  $playing  activity name => Discord activity type
     */
    public function __construct(
        public readonly string $guildDiscordId,
        public readonly string $memberDiscordId,
        public readonly array $playing,
        public readonly string $at,
    ) {}

    public function handle(Tracking $tracking, RecordActivities $record): void
    {
        $guild = $tracking->guild($this->guildDiscordId);
        $member = $tracking->member($this->memberDiscordId);

        if ($guild instanceof Guild && $member instanceof DiscordUser) {
            $record->handle($guild, $member, $this->playing, CarbonImmutable::parse($this->at));
        }
    }
}
