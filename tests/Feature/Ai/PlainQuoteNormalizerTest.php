<?php

declare(strict_types=1);

use App\Ai\CaptureInput;
use App\Ai\PlainQuoteNormalizer;
use App\Enums\SaidAtPrecision;
use App\Quotes\Data\NormalizedLine;
use App\Quotes\Data\NormalizedQuote;
use Carbon\CarbonImmutable;

function plain(CaptureInput $input): NormalizedQuote
{
    return app(PlainQuoteNormalizer::class)->normalize($input);
}

it('keeps a message as one line by its author with the exact time', function (): void {
    $sentAt = CarbonImmutable::parse('2026-11-20 21:15:00');

    $quote = plain(new CaptureInput(
        text: "  I'm not drunk,\nI'm *philosophical*  ",
        knownSpeaker: new NormalizedLine('Martin', '', '42'),
        messageTime: $sentAt,
    ));

    expect($quote->lines)->toHaveCount(1)
        ->and($quote->lines[0]->speakerName)->toBe('Martin')
        ->and($quote->lines[0]->speakerDiscordId)->toBe('42')
        ->and($quote->lines[0]->text)->toBe("I'm not drunk,\nI'm *philosophical*")
        ->and($quote->saidAt?->equalTo($sentAt))->toBeTrue()
        ->and($quote->saidAtPrecision)->toBe(SaidAtPrecision::Exact);
});

it('replaces discord mentions with names', function (): void {
    $quote = plain(new CaptureInput(
        text: 'blame <@99> and <@!77> for this',
        knownSpeaker: new NormalizedLine('Martin', '', '42'),
        mentionNames: ['99' => 'Bo', '77' => 'Kim'],
    ));

    expect($quote->lines[0]->text)->toBe('blame @Bo and @Kim for this');
});

it('splits a dialogue written as "Name: text" lines', function (): void {
    $quote = plain(new CaptureInput(text: "Martin: I never said that\nBo: You literally did\n\nMartin: prove it"));

    expect(array_map(fn (NormalizedLine $line): array => [$line->speakerName, $line->text], $quote->lines))->toBe([
        ['Martin', 'I never said that'],
        ['Bo', 'You literally did'],
        ['Martin', 'prove it'],
    ]);
});

it('understands "X said \'Y\'" with the rest as context', function (): void {
    $quote = plain(new CaptureInput(text: 'Martin said "I can totally fix that" right before breaking it'));

    expect($quote->lines[0]->speakerName)->toBe('Martin')
        ->and($quote->lines[0]->text)->toBe('I can totally fix that')
        ->and($quote->context)->toBe('right before breaking it');
});

it('uses the given speaker for slash commands', function (): void {
    $quote = plain(new CaptureInput(
        text: 'cheese is a vegetable',
        speakerHint: new NormalizedLine("Martin's uncle", ''),
        context: 'at the barbecue',
    ));

    expect($quote->lines[0]->speakerName)->toBe("Martin's uncle")
        ->and($quote->context)->toBe('at the barbecue');
});

it('marks unknown speakers', function (): void {
    expect(plain(new CaptureInput(text: 'who even said this'))->lines[0]->speakerName)->toBe('Unknown');
});

it('parses simple "when" phrases', function (string $when, string $expected, SaidAtPrecision $precision): void {
    $quote = plain(new CaptureInput(text: 'A: b', when: $when, now: CarbonImmutable::parse('2026-11-27 12:00')));

    expect($quote->saidAt?->toDateString())->toBe($expected)
        ->and($quote->saidAtPrecision)->toBe($precision);
})->with([
    'yesterday' => ['yesterday', '2026-11-26', SaidAtPrecision::Day],
    'iso date' => ['2026-10-31', '2026-10-31', SaidAtPrecision::Day],
    'today' => ['today', '2026-11-27', SaidAtPrecision::Day],
]);

it('keeps vague "when" phrases as context', function (): void {
    $quote = plain(new CaptureInput(text: 'A: b', when: 'last summer at the lake'));

    expect($quote->saidAt)->toBeNull()
        ->and($quote->context)->toBe('last summer at the lake');
});
