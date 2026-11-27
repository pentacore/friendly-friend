<?php

declare(strict_types=1);

use App\Models\Guild;
use App\Models\User;
use App\Stats\Jobs\PurgeMemberStats;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Queue::fake();
    $this->user = User::factory()->memberOf(Guild::factory()->create())->create();
});

it('shows whether stats are on', function (): void {
    $this->actingAs($this->user)
        ->get(route('privacy.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->component('settings/Privacy')->where('opted_out_at', null));
});

it('turns stats off and queues the purge', function (): void {
    $this->actingAs($this->user)
        ->put(route('privacy.update'), ['tracking' => false])
        ->assertRedirect(route('privacy.edit'));

    expect($this->user->discordUser->fresh()?->tracking_opted_out_at)->not->toBeNull();
    Queue::assertPushed(PurgeMemberStats::class);
});

it('turns stats back on', function (): void {
    $this->user->discordUser->forceFill(['tracking_opted_out_at' => now()])->save();

    $this->actingAs($this->user)->put(route('privacy.update'), ['tracking' => true]);

    expect($this->user->discordUser->fresh()?->tracking_opted_out_at)->toBeNull();
    Queue::assertNotPushed(PurgeMemberStats::class);
});

it('requires a choice', function (): void {
    $this->actingAs($this->user)->put(route('privacy.update'))->assertSessionHasErrors('tracking');
});

it('is only for logged-in people', function (): void {
    $this->get(route('privacy.edit'))->assertRedirect();
    $this->put(route('privacy.update'), ['tracking' => false])->assertRedirect();
});
