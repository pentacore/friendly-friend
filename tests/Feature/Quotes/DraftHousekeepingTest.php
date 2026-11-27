<?php

declare(strict_types=1);

use App\Ai\PlainQuoteNormalizer;
use App\Ai\QuoteNormalizer;
use App\Discord\DiscordClient;
use App\Discord\Interactions\QuoteCommands;
use App\Discord\Jobs\RegisterGuildCommands;
use App\Discord\Testing\FakeDiscordClient;
use App\Models\Guild;
use App\Models\Quote;
use App\Quotes\Actions\ConfirmQuote;
use App\Quotes\Discord\QuoteInteractions;
use App\Quotes\Jobs\PruneExpiredDrafts;
use Illuminate\Console\Scheduling\Schedule;
use Tests\Support\DiscordPayloads as P;

it('deletes drafts older than the draft lifetime', function (): void {
    $old = Quote::factory()->draft()->create(['updated_at' => now()->subMinutes(16)]);
    $fresh = Quote::factory()->draft()->create();
    $published = Quote::factory()->create(['updated_at' => now()->subYear()]);

    dispatch_sync(new PruneExpiredDrafts);

    expect(Quote::query()->pluck('id')->sort()->values()->all())->toBe(collect([$fresh->id, $published->id])->sort()->values()->all())
        ->and(Quote::query()->find($old->id))->toBeNull();
});

it('prunes every minute', function (): void {
    $event = collect(app(Schedule::class)->events())->first(fn ($e): bool => str_contains((string) $e->description, PruneExpiredDrafts::class));

    expect($event?->expression)->toBe('* * * * *');
});

it('registers commands in enabled servers and removes them from disabled ones', function (): void {
    config()->set('services.discord.client_id', 'app-1');
    app()->instance(DiscordClient::class, $discord = new FakeDiscordClient);
    Guild::factory()->create(['discord_id' => '1']);
    Guild::factory()->disabled()->create(['discord_id' => '2']);

    dispatch_sync(new RegisterGuildCommands('1'));
    dispatch_sync(new RegisterGuildCommands('2'));

    expect(array_column($discord->commandsFor('1') ?? [], 'name'))->toBe(['quote', 'Save quote', 'privacy'])
        ->and($discord->commandsFor('2'))->toBe([]);
});

it('defines /quote add, random, import and the Save quote menu', function (): void {
    $definitions = QuoteCommands::definitions();

    expect(array_column($definitions[0]['options'], 'name'))->toBe(['add', 'random', 'import'])
        ->and($definitions[1])->toMatchArray(['type' => 3, 'name' => 'Save quote']);
});

it('turns an expired command preview into "expired" through its interaction token', function (): void {
    config()->set('discord.bot_token', 'token');
    app()->bind(QuoteNormalizer::class, PlainQuoteNormalizer::class);
    app()->instance(DiscordClient::class, $discord = new FakeDiscordClient);
    Guild::factory()->create(['discord_id' => '555']);

    app(QuoteInteractions::class)->handle(P::slash('add', [['name' => 'text', 'type' => 3, 'value' => 'Bo: cheese is a vegetable']]));
    $draft = Quote::query()->sole();
    expect($draft->preview_token)->toBe('tok-1');

    $this->travel(15)->minutes();
    dispatch_sync(new PruneExpiredDrafts);

    expect(Quote::query()->count())->toBe(0)
        ->and(array_last($discord->interactionResponses()))->toBe(['token' => 'tok-1', 'message' => ['content' => PruneExpiredDrafts::EXPIRED, 'components' => []]]);
});

it('turns an expired DM preview into "expired"', function (): void {
    config()->set('discord.bot_token', 'token');
    app()->instance(DiscordClient::class, $discord = new FakeDiscordClient);
    $draft = Quote::factory()->draft()->withLine()->create(['preview_channel_id' => 'dm-42', 'preview_message_id' => '2001']);

    $this->travel(15)->minutes();
    dispatch_sync(new PruneExpiredDrafts);

    expect($discord->editedMessages())->toBe([['channel_id' => 'dm-42', 'message_id' => '2001', 'message' => ['content' => PruneExpiredDrafts::EXPIRED, 'components' => []]]])
        ->and($draft->fresh())->toBeNull();
});

it('forgets where the preview was once the quote is saved', function (): void {
    $quote = Quote::factory()->draft()->withLine()->create(['preview_token' => 'tok', 'preview_channel_id' => 'dm', 'preview_message_id' => '1']);

    app(ConfirmQuote::class)->handle($quote->load('lines'), $quote->submittedBy);

    expect($quote->fresh())->preview_token->toBeNull()->preview_message_id->toBeNull();
});
