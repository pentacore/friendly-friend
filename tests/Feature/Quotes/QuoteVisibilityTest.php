<?php

declare(strict_types=1);

use App\Access\AccessSnapshot;
use App\Enums\QuoteStatus;
use App\Http\Resources\QuoteResource;
use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\Quote;
use App\Models\QuoteVote;
use App\Models\User;
use App\Quotes\QuoteRules;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->mine = Guild::factory()->create();
    $this->other = Guild::factory()->create();
    $this->user = User::factory()->memberOf($this->mine)->create();
    $this->access = AccessSnapshot::for($this->user);
});

it('shows published quotes of guilds the user can see', function (): void {
    $visible = Quote::factory()->for($this->mine)->create();
    Quote::factory()->for($this->other)->create();
    Quote::factory()->for($this->mine)->draft()->create();
    Quote::factory()->for($this->mine)->create(['status' => QuoteStatus::PendingReview]);

    expect(Quote::query()->visibleTo($this->access)->pluck('id')->all())->toBe([$visible->id]);
});

it('lets owners see every enabled guild', function (): void {
    config()->set('discord.owner_ids', ['4242']);
    $owner = User::factory()->forDiscordUser(DiscordUser::factory()->create(['discord_id' => '4242']))->create();
    Quote::factory()->for($this->mine)->create();
    Quote::factory()->for($this->other)->create();
    Quote::factory()->for(Guild::factory()->disabled()->create())->create();

    expect(Quote::query()->visibleTo(AccessSnapshot::for($owner))->count())->toBe(2);
});

it('lets submitters edit their own quotes and admins moderate their guild', function (): void {
    $own = Quote::factory()->for($this->mine)->create(['submitted_by_discord_user_id' => $this->user->discord_user_id]);
    $someoneElses = Quote::factory()->for($this->mine)->create();
    $admin = User::factory()->memberOf($this->mine, ['900'])->create();
    $adminAccess = AccessSnapshot::for($admin);

    expect(QuoteRules::canUpdate($this->access, $own))->toBeTrue()
        ->and(QuoteRules::canDelete($this->access, $own))->toBeFalse()
        ->and(QuoteRules::canUpdate($this->access, $someoneElses))->toBeFalse()
        ->and(QuoteRules::canUpdate($adminAccess, $someoneElses))->toBeTrue()
        ->and(QuoteRules::canDelete($adminAccess, $someoneElses))->toBeTrue()
        ->and(QuoteRules::canDelete($adminAccess, Quote::factory()->for($this->other)->create()))->toBeFalse()
        ->and(Gate::forUser($this->user)->allows('update', $own))->toBeTrue();
});

it('keeps the search text in sync with the lines and context', function (): void {
    $quote = Quote::factory()->for($this->mine)->withLine('Martin', 'cheese is a vegetable')->create(['context' => 'at the barbecue']);
    $quote->refreshSearchText();

    expect(Quote::query()->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", ['vegetable barbecue'])->pluck('id')->all())->toBe([$quote->id]);
});

it('presents a quote with its permissions and the user\'s vote', function (): void {
    $quote = Quote::factory()->for($this->mine)->withLine('Martin', 'hi')->create(['submitted_by_discord_user_id' => $this->user->discord_user_id, 'score' => 3]);
    QuoteVote::query()->create(['quote_id' => $quote->id, 'user_id' => $this->user->id, 'value' => 1]);
    $this->actingAs($this->user);
    $quote->load(['lines.person', 'guild', 'submittedBy', 'myVote' => fn ($q) => $q->where('user_id', $this->user->id)]);

    $data = new QuoteResource($quote)->resolve(request()->setUserResolver(fn () => $this->user));

    expect($data['lines'][0])->toBe(['speaker_name' => 'Martin', 'text' => 'hi', 'person' => null])
        ->and($data['score'])->toBe(3)
        ->and($data['my_vote'])->toBe(1)
        ->and($data['can'])->toBe(['update' => true, 'delete' => false, 'restore' => false])
        ->and($data['guild']['id'])->toBe($this->mine->id);
});
