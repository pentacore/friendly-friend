<?php

declare(strict_types=1);

namespace App\Stats\Jobs;

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Stats\Actions\RecordVoiceState;
use App\Stats\Tracking;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;

/**
 * A member joined, moved between or left voice channels (channel null).
 */
#[Tries(3)]
final class IngestVoiceState implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $guildDiscordId,
        public readonly string $memberDiscordId,
        public readonly ?string $channelId,
        public readonly string $at,
    ) {}

    public function handle(Tracking $tracking, RecordVoiceState $record): void
    {
        $guild = $tracking->guild($this->guildDiscordId);
        $member = $tracking->member($this->memberDiscordId);

        if ($guild instanceof Guild && $member instanceof DiscordUser) {
            $record->handle($guild, $member, $this->channelId, CarbonImmutable::parse($this->at));
        }
    }
}
