<?php

declare(strict_types=1);

use App\Access\AccessSnapshot;
use App\Models\Event;
use App\Models\User;
use App\Planning\EventRules;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', fn (User $user, int|string $id): bool => (string) $user->id === (string) $id);

// Live quote updates (scores) for everyone who can see that server's quotes.
Broadcast::channel('guilds.{guildId}.quotes', fn (User $user, int $guildId): bool => AccessSnapshot::for($user)->canSeeGuild($guildId));

// "Something changed" pings for everyone viewing an event.
Broadcast::channel('events.{eventId}', fn (User $user, int $eventId): bool => ($event = Event::query()->find($eventId)) !== null
    && EventRules::canView(AccessSnapshot::for($user), $event));
