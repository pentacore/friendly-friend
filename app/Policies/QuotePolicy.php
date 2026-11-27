<?php

declare(strict_types=1);

namespace App\Policies;

use App\Access\AccessSnapshot;
use App\Models\Quote;
use App\Models\User;
use App\Quotes\QuoteRules;

final class QuotePolicy
{
    public function view(User $user, Quote $quote): bool
    {
        return QuoteRules::canView($this->access($user), $quote);
    }

    public function update(User $user, Quote $quote): bool
    {
        return QuoteRules::canUpdate($this->access($user), $quote);
    }

    public function delete(User $user, Quote $quote): bool
    {
        return QuoteRules::canDelete($this->access($user), $quote);
    }

    public function review(User $user, Quote $quote): bool
    {
        return QuoteRules::canReview($this->access($user), $quote);
    }

    private function access(User $user): AccessSnapshot
    {
        $snapshot = AccessSnapshot::forRequest(request());

        return $snapshot instanceof AccessSnapshot && $snapshot->userId === $user->id ? $snapshot : AccessSnapshot::for($user);
    }
}
