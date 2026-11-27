<?php

declare(strict_types=1);

use App\Ai\PlainQuoteNormalizer;
use App\Ai\QuoteNormalizer;
use App\Discord\DiscordClient;
use App\Discord\Testing\FakeDiscordClient;
use App\Enums\QuoteSource;
use App\Enums\QuoteStatus;
use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\Quote;
use App\Models\QuoteImport;
use App\Quotes\Imports\StartChannelImport;
use App\Quotes\Jobs\ImportChannelQuotes;
use Laravel\Ai\Classification;
use Laravel\Ai\Responses\Data\BooleanAnswer;
use Tests\Support\DiscordPayloads as P;

beforeEach(function (): void {
    config()->set('quotes.ai.providers', []);
    app()->bind(QuoteNormalizer::class, PlainQuoteNormalizer::class);
    app()->instance(DiscordClient::class, $this->discord = new FakeDiscordClient);
    $this->guild = Guild::factory()->create(['discord_id' => '555']);
    $this->admin = DiscordUser::factory()->create();
});

it('turns quote-looking messages into quotes waiting for review', function (): void {
    $this->discord->withChannelMessages('321', [
        P::message('5', 'Martin: I never said that', '50'),
        P::message('4', 'lol', '50'),
        P::message('3', 'https://example.com/cat.gif', '50'),
        P::message('2', '"cheese is a vegetable" - uncle Bo', '51'),
        P::message('1', 'beep', '9', ['author' => ['id' => '9', 'username' => 'bot', 'bot' => true]]),
    ]);

    app(StartChannelImport::class)->handle($this->guild, $this->admin, '321');

    $import = QuoteImport::query()->sole();
    expect($import->status)->toBe('done')
        ->and($import->messages_scanned)->toBe(5)
        ->and($import->quotes_found)->toBe(2);

    $quotes = Quote::query()->with('lines')->orderBy('source_message_id')->get();
    expect($quotes->pluck('source_message_id')->all())->toBe(['2', '5'])
        ->and($quotes->every(fn (Quote $q): bool => $q->status === QuoteStatus::PendingReview && $q->source === QuoteSource::ChannelImport))->toBeTrue()
        ->and($quotes[1]->lines[0]->speaker_name)->toBe('Martin')
        ->and($quotes[1]->said_at?->toDateString())->toBe('2026-11-20');
});

it('skips messages that are already quotes', function (): void {
    Quote::factory()->for($this->guild)->create(['source_message_id' => '5']);
    $this->discord->withChannelMessages('321', [P::message('5', 'Martin: hi', '50')]);

    app(StartChannelImport::class)->handle($this->guild, $this->admin, '321');

    expect(QuoteImport::query()->sole()->quotes_found)->toBe(0);
});

it('pages through long histories and resumes from the cursor', function (): void {
    $messages = array_map(fn (int $i): array => P::message((string) (10_000 - $i), "Person{$i}: line {$i}", '50'), range(0, 649));
    $this->discord->withChannelMessages('321', $messages);

    app(StartChannelImport::class)->handle($this->guild, $this->admin, '321');

    // 5 pages per run, then a follow-up job finishes the rest (sync queue).
    $import = QuoteImport::query()->sole();
    expect($import->status)->toBe('done')
        ->and($import->messages_scanned)->toBe(650)
        ->and($import->quotes_found)->toBe(650);
});

it('uses Jev through OpenRouter to decide what is a quote', function (): void {
    config()->set('ai.providers.openrouter.key', 'or-key');
    Classification::fake(fn ($prompt): array => ['quote' => new BooleanAnswer(str_contains((string) json_encode($prompt->state), 'Martin') ? 0.93 : 0.08)]);
    $this->discord->withChannelMessages('321', [
        P::message('2', 'Martin said nothing memorable today', '50'),
        P::message('1', 'Bo: totally a quote by heuristics', '50'),
    ]);

    app(StartChannelImport::class)->handle($this->guild, $this->admin, '321');

    expect(Quote::query()->pluck('source_message_id')->all())->toBe(['2']);
    Classification::assertClassified(fn ($prompt): bool => $prompt->provider->name() === 'openrouter' && $prompt->model === '~typesafe/jev-latest');
});

it('records a failed import', function (): void {
    $import = QuoteImport::query()->create([
        'guild_id' => $this->guild->id, 'channel_id' => 'x', 'requested_by_discord_user_id' => $this->admin->id, 'status' => 'queued',
    ]);
    $this->discord->failChannelMessagesWith(new RuntimeException('Missing Access'));

    expect(fn () => dispatch_sync(new ImportChannelQuotes($import->id)))->toThrow(RuntimeException::class);
    expect($import->refresh()->status)->toBe('failed')
        ->and($import->error)->toBe('Missing Access');
});
