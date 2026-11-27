<?php

declare(strict_types=1);

use App\Ai\PlainQuoteNormalizer;
use App\Ai\QuoteNormalizer;
use App\Discord\DiscordClient;
use App\Discord\Testing\FakeDiscordClient;
use App\Enums\QuoteSource;
use App\Models\Guild;
use App\Models\Quote;
use App\Quotes\Jobs\CaptureFromMention;
use Tests\Support\DiscordPayloads as P;

beforeEach(function (): void {
    config()->set('services.discord.client_id', '1000');
    app()->bind(QuoteNormalizer::class, PlainQuoteNormalizer::class);
    app()->instance(DiscordClient::class, $this->discord = new FakeDiscordClient);
    Guild::factory()->create(['discord_id' => '555']);
});

/**
 * A message by member 42 that mentions the bot.
 *
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function mentionMessage(string $content, array $extra = []): array
{
    return P::message('m-1', $content, '42', [
        'guild_id' => '555',
        'author' => P::member()['user'],
        'member' => ['roles' => ['100'], 'nick' => null],
        'mentions' => [['id' => '1000', 'username' => 'friendly-bot']],
        ...$extra,
    ]);
}

it('quotes the replied-to message and DMs the preview', function (): void {
    dispatch_sync(new CaptureFromMention(mentionMessage('<@1000>', ['referenced_message' => P::message('999', 'i can fix it', '50')])));

    $quote = Quote::query()->with('lines')->sole();
    expect($quote->source)->toBe(QuoteSource::ReplyMention)
        ->and($quote->source_message_id)->toBe('999')
        ->and($quote->lines[0]->speaker_name)->toBe('Bo')
        ->and($this->discord->directMessages()[0]['user_id'])->toBe('42')
        ->and(FakeDiscordClient::content($this->discord->directMessages()[0]['message']))->toContain('Preview');
});

it('quotes free text written to the bot', function (): void {
    dispatch_sync(new CaptureFromMention(mentionMessage('<@1000> Bo said "I can totally fix that" before breaking it')));

    $quote = Quote::query()->with('lines')->sole();
    expect($quote->source)->toBe(QuoteSource::MentionFreetext)
        ->and($quote->lines[0]->speaker_name)->toBe('Bo')
        ->and($quote->lines[0]->text)->toBe('I can totally fix that')
        ->and($quote->context)->toBe('before breaking it');
});

it('discards the draft and replies in the channel when DMs are closed', function (): void {
    $this->discord->withClosedDms('42');

    dispatch_sync(new CaptureFromMention(mentionMessage('<@1000> Bo: hi')));

    expect(Quote::query()->count())->toBe(0);
    $this->discord->assertSent('777', "couldn't DM you");
    expect($this->discord->sentMessages()[0]['message']['message_reference'])->toBe(['message_id' => 'm-1']);
});

it('ignores a bare mention and members without access', function (array $message): void {
    dispatch_sync(new CaptureFromMention($message));

    expect(Quote::query()->count())->toBe(0)
        ->and($this->discord->directMessages())->toBe([]);
})->with([
    'bare mention' => [mentionMessage('<@1000>')],
    'no access' => [mentionMessage('<@1000> Bo: hi', ['member' => ['roles' => ['5']]])],
]);
