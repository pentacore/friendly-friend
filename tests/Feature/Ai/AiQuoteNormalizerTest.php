<?php

declare(strict_types=1);

use App\Ai\Agents\QuoteNormalizerAgent;
use App\Ai\AiQuoteNormalizer;
use App\Ai\CaptureInput;
use App\Ai\QuoteNormalizer;
use App\Enums\SaidAtPrecision;
use App\Quotes\Data\NormalizedLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    config()->set('quotes.ai.providers', ['anthropic', 'openai']);
    config()->set('ai.providers.anthropic.key', 'test-key');
    config()->set('ai.providers.openai.key', '');
});

it('is the bound quote normalizer', function (): void {
    expect(app(QuoteNormalizer::class))->toBeInstanceOf(AiQuoteNormalizer::class);
});

it('maps the AI answer to lines, context and time, linking mentioned speakers', function (): void {
    QuoteNormalizerAgent::fake([[
        'lines' => [
            ['speaker' => 'Bo', 'text' => 'I can fix it'],
            ['speaker' => 'Martin', 'text' => 'You really cannot'],
        ],
        'context' => 'about the dishwasher',
        'said_at' => '2026-11-26',
        'said_at_precision' => 'day',
    ]]);

    $quote = app(AiQuoteNormalizer::class)->normalize(new CaptureInput(
        text: '<@99> said "i can fix it" and martin went "you really cannot" yesterday',
        mentionNames: ['99' => 'Bo'],
        now: CarbonImmutable::parse('2026-11-27 12:00'),
    ));

    expect($quote->lines)->toHaveCount(2)
        ->and($quote->lines[0]->speakerName)->toBe('Bo')
        ->and($quote->lines[0]->speakerDiscordId)->toBe('99')
        ->and($quote->lines[1]->speakerDiscordId)->toBeNull()
        ->and($quote->context)->toBe('about the dishwasher')
        ->and($quote->saidAt?->toDateString())->toBe('2026-11-26')
        ->and($quote->saidAtPrecision)->toBe(SaidAtPrecision::Day)
        ->and($quote->aiOutput)->toHaveKey('lines');

    QuoteNormalizerAgent::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, '@Bo'));
});

it('keeps a known speaker and exact message time no matter what the AI says', function (): void {
    QuoteNormalizerAgent::fake([[
        'lines' => [['speaker' => 'Someone Else', 'text' => "I'm not drunk, I'm philosophical"]],
        'context' => null,
        'said_at' => null,
        'said_at_precision' => null,
    ]]);
    $sentAt = CarbonImmutable::parse('2026-11-20 21:15');

    $quote = app(AiQuoteNormalizer::class)->normalize(new CaptureInput(
        text: 'im not drunk, im philosophical',
        knownSpeaker: new NormalizedLine('Martin', '', '42'),
        messageTime: $sentAt,
    ));

    expect($quote->lines)->toHaveCount(1)
        ->and($quote->lines[0]->speakerName)->toBe('Martin')
        ->and($quote->lines[0]->speakerDiscordId)->toBe('42')
        ->and($quote->lines[0]->text)->toBe("I'm not drunk, I'm philosophical")
        ->and($quote->saidAt?->equalTo($sentAt))->toBeTrue();
});

it('falls back to plain parsing when the AI fails', function (): void {
    Log::spy();
    QuoteNormalizerAgent::fake(fn (): never => throw new RuntimeException('provider down'));

    $quote = app(AiQuoteNormalizer::class)->normalize(new CaptureInput(text: "Martin: hi\nBo: bye"));

    expect($quote->lines)->toHaveCount(2)
        ->and($quote->aiOutput)->toBeNull();
    Log::shouldHaveReceived('warning')->once();
});

it('falls back to plain parsing when the AI returns no usable lines', function (): void {
    QuoteNormalizerAgent::fake([['lines' => [], 'context' => null, 'said_at' => null, 'said_at_precision' => null]]);

    $quote = app(AiQuoteNormalizer::class)->normalize(new CaptureInput(text: 'Martin: hi'));

    expect($quote->lines[0]->speakerName)->toBe('Martin');
});

it('does not call any AI when no provider key is configured', function (): void {
    config()->set('ai.providers.anthropic.key', '');
    QuoteNormalizerAgent::fake()->preventStrayPrompts();

    $quote = app(AiQuoteNormalizer::class)->normalize(new CaptureInput(text: 'Martin: hi'));

    expect($quote->lines[0]->text)->toBe('hi');
    QuoteNormalizerAgent::assertNeverPrompted();
});

it('only offers providers that have a key, in configured order', function (): void {
    config()->set('ai.providers.openai.key', 'k');

    expect(app(AiQuoteNormalizer::class)->providers())->toBe(['anthropic', 'openai']);
});
