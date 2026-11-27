<?php

declare(strict_types=1);

use App\Discord\DiscordClient;
use App\Discord\Gateway\GatewayEventRouter;
use App\Discord\Interactions\InteractionAck;
use App\Discord\Interactions\InteractionAcknowledger;
use App\Discord\Testing\FakeDiscordClient;
use App\Discord\Testing\FakeInteractionAcknowledger;
use App\Models\DiscordUser;
use App\Quotes\Jobs\HandleQuoteInteraction;
use App\Stats\Discord\PrivacyInteractions;
use App\Stats\Jobs\HandlePrivacyCommand;
use App\Stats\Jobs\PurgeMemberStats;
use Illuminate\Support\Facades\Queue;
use Tests\Support\DiscordPayloads as P;

beforeEach(function (): void {
    Queue::fake();
    app()->instance(DiscordClient::class, $this->discord = new FakeDiscordClient);
});

function privacy(string $subcommand): array
{
    return P::slash($subcommand, command: 'privacy');
}

it('acknowledges privately and queues its own job', function (): void {
    app()->instance(InteractionAcknowledger::class, $acks = new FakeInteractionAcknowledger);

    app(GatewayEventRouter::class)->route('INTERACTION_CREATE', privacy('status'));

    expect(InteractionAck::body(privacy('status')))->toBe(['type' => 5, 'data' => ['flags' => 64]])
        ->and($acks->acknowledged)->toHaveCount(1);
    Queue::assertPushed(HandlePrivacyCommand::class);
    Queue::assertNotPushed(HandleQuoteInteraction::class);
});

it('tells you whether stats are on', function (): void {
    app(PrivacyInteractions::class)->handle(privacy('status'));

    expect(FakeDiscordClient::content($this->discord->lastResponse()))->toContain('Stats are **on**');
});

it('opts you out, even if you never logged in', function (): void {
    app(PrivacyInteractions::class)->handle(privacy('optout'));

    $member = DiscordUser::query()->where('discord_id', '42')->sole();
    expect($member->tracking_opted_out_at)->not->toBeNull()
        ->and(FakeDiscordClient::content($this->discord->lastResponse()))->toContain('being deleted');
    Queue::assertPushed(PurgeMemberStats::class);
});

it('opts you back in', function (): void {
    DiscordUser::factory()->create(['discord_id' => '42', 'tracking_opted_out_at' => now()]);

    app(PrivacyInteractions::class)->handle(privacy('optin'));

    expect(DiscordUser::query()->where('discord_id', '42')->value('tracking_opted_out_at'))->toBeNull();
});
