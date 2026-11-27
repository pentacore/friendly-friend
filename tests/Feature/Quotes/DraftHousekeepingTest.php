<?php

declare(strict_types=1);

use App\Discord\DiscordClient;
use App\Discord\Interactions\QuoteCommands;
use App\Discord\Jobs\RegisterGuildCommands;
use App\Discord\Testing\FakeDiscordClient;
use App\Models\Guild;
use App\Models\Quote;
use App\Quotes\Jobs\PruneExpiredDrafts;
use Illuminate\Console\Scheduling\Schedule;

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

    expect($discord->commandsFor('1'))->toBe(QuoteCommands::definitions())
        ->and($discord->commandsFor('2'))->toBe([]);
});

it('defines /quote add, random, import and the Save quote menu', function (): void {
    $definitions = QuoteCommands::definitions();

    expect(array_column($definitions[0]['options'], 'name'))->toBe(['add', 'random', 'import'])
        ->and($definitions[1])->toMatchArray(['type' => 3, 'name' => 'Save quote']);
});
