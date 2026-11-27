<?php

declare(strict_types=1);

use App\Discord\Gateway\GatewayEventRouter;
use App\Discord\Interactions\CustomId;
use App\Discord\Interactions\DraftEditForm;
use App\Discord\Interactions\InteractionAck;
use App\Discord\Interactions\InteractionAcknowledger;
use App\Discord\Testing\FakeInteractionAcknowledger;
use App\Models\Quote;
use App\Quotes\Jobs\CaptureFromMention;
use App\Quotes\Jobs\HandleQuoteInteraction;
use Illuminate\Support\Facades\Queue;
use Tests\Support\DiscordPayloads as P;

beforeEach(function (): void {
    Queue::fake();
    config()->set('services.discord.client_id', '1000');
    app()->instance(InteractionAcknowledger::class, $this->acks = new FakeInteractionAcknowledger);
});

it('acknowledges commands privately, except a random quote which is public', function (): void {
    expect(InteractionAck::body(P::slash('add')))->toBe(['type' => 5, 'data' => ['flags' => 64]])
        ->and(InteractionAck::body(P::saveQuoteMenu(P::message())))->toBe(['type' => 5, 'data' => ['flags' => 64]])
        ->and(InteractionAck::body(P::slash('random')))->toBe(['type' => 5]);
});

it('acknowledges save and cancel clicks and form submissions as deferred updates', function (): void {
    expect(InteractionAck::body(P::button(CustomId::for('save', 1))))->toBe(['type' => 6])
        ->and(InteractionAck::body(P::button(CustomId::for('cancel', 1))))->toBe(['type' => 6])
        ->and(InteractionAck::body(P::modal(CustomId::for('modal', 1), ['lines' => 'A: b'])))->toBe(['type' => 6]);
});

it('answers an edit click with the cached form, or says the draft expired', function (): void {
    $quote = Quote::factory()->draft()->withLine('Martin', 'hi')->create();
    app(DraftEditForm::class)->remember($quote->load('lines'));

    $body = InteractionAck::body(P::button(CustomId::for('edit', $quote->id)));
    expect($body['type'])->toBe(9)
        ->and($body['data']['custom_id'])->toBe(CustomId::for('modal', $quote->id))
        ->and($body['data']['components'][0]['components'][0]['value'])->toBe('Martin: hi');

    expect(InteractionAck::body(P::button(CustomId::for('edit', 12345))))->toMatchArray(['type' => 4]);
});

it('ignores interactions that are not ours', function (): void {
    expect(InteractionAck::body(P::button('some-other-bot:thing')))->toBeNull()
        ->and(InteractionAck::body(['type' => 2, 'data' => ['name' => 'other']]))->toBeNull();
});

it('acknowledges in the bot and queues the work', function (): void {
    app(GatewayEventRouter::class)->route('INTERACTION_CREATE', P::slash('add'));

    expect($this->acks->acknowledged)->toHaveCount(1)
        ->and($this->acks->acknowledged[0]['id'])->toBe('int-1')
        ->and($this->acks->acknowledged[0]['token'])->toBe('tok-1');
    Queue::assertPushed(HandleQuoteInteraction::class);
});

it('does not queue work when the edit form was the answer', function (): void {
    $quote = Quote::factory()->draft()->withLine()->create();
    app(DraftEditForm::class)->remember($quote->load('lines'));

    app(GatewayEventRouter::class)->route('INTERACTION_CREATE', P::button(CustomId::for('edit', $quote->id)));

    expect($this->acks->acknowledged)->toHaveCount(1);
    Queue::assertNothingPushed();
});

it('queues a capture when a guild message mentions the bot', function (): void {
    app(GatewayEventRouter::class)->route('MESSAGE_CREATE', P::message(extra: [
        'guild_id' => '555',
        'mentions' => [['id' => '1000', 'username' => 'friendly-bot']],
    ]));

    Queue::assertPushed(CaptureFromMention::class);
});

it('ignores messages without a bot mention, from bots, or in DMs', function (array $extra): void {
    app(GatewayEventRouter::class)->route('MESSAGE_CREATE', P::message(extra: $extra));

    Queue::assertNothingPushed();
})->with([
    'no mention' => [['guild_id' => '555', 'mentions' => [['id' => '7']]]],
    'from a bot' => [['guild_id' => '555', 'mentions' => [['id' => '1000']], 'author' => ['id' => '9', 'username' => 'b', 'bot' => true]]],
    'direct message' => [['mentions' => [['id' => '1000']]]],
]);
