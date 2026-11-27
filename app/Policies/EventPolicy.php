<?php

declare(strict_types=1);

namespace App\Policies;

use App\Access\AccessSnapshot;
use App\Models\Event;
use App\Models\User;
use App\Planning\EventRules;

/**
 * Event permissions for gates and form requests; the rules live in App\Planning\EventRules.
 */
final class EventPolicy
{
    public function view(User $user, Event $event): bool
    {
        return EventRules::canView($this->access($user), $event);
    }

    public function update(User $user, Event $event): bool
    {
        return EventRules::canEdit($this->access($user), $event);
    }

    public function participate(User $user, Event $event): bool
    {
        return EventRules::canParticipate($this->access($user), $event);
    }

    private function access(User $user): AccessSnapshot
    {
        $snapshot = AccessSnapshot::forRequest(request());

        return $snapshot instanceof AccessSnapshot && $snapshot->userId === $user->id ? $snapshot : AccessSnapshot::for($user);
    }
}
