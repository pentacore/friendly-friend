<?php

declare(strict_types=1);

use App\Ai\PlainQuoteNormalizer;
use App\Ai\QuoteNormalizer;
use App\Discord\DiscordClient;
use App\Discord\Testing\FakeDiscordClient;
use App\Enums\QuoteSource;
use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\Person;
use App\Models\PersonAlias;
use App\Models\Quote;
use App\Quotes\Jobs\CaptureFromMention;
use Laravel\Ai\Classification;
use Laravel\Ai\Responses\Data\ChoiceAnswer;
use Tests\Support\DiscordPayloads as P;

beforeEach(function (): void {
    config()->set('services.discord.client_id', '1000');
    app()->bind(QuoteNormalizer::class, PlainQuoteNormalizer::class);
    app()->instance(DiscordClient::class, $this->discord = new FakeDiscordClient);
    $this->guild = Guild::factory()->create(['discord_id' => '555']);
});

/**
 * Jev (through OpenRouter) answers the mention's intent with this choice.
 */
function jevSaysIntent(string $intent, float $probability = 0.9): void
{
    config()->set('ai.providers.openrouter.key', 'or-key');
    Classification::fake(fn (): array => ['intent' => new ChoiceAnswer($intent, [$intent => $probability], $probability)]);
}

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

it('posts a random quote in the channel when the mention asks for one', function (): void {
    Quote::factory()->for($this->guild)->withLine('Bo', 'i can fix it')->create();
    jevSaysIntent('random_quote');

    dispatch_sync(new CaptureFromMention(mentionMessage('<@1000> give me a random quote')));

    expect(Quote::query()->count())->toBe(1)
        ->and($this->discord->directMessages())->toBe([]);
    $this->discord->assertSent('777', 'i can fix it');
    Classification::assertClassified(fn ($prompt): bool => $prompt->provider->name() === 'openrouter');
});

it('picks the random quote from a mentioned member', function (): void {
    $bo = Person::factory()->forDiscordUser(DiscordUser::factory()->create(['discord_id' => '50']))->create(['display_name' => 'Bo']);
    Quote::factory()->for($this->guild)->withLine('Ann', 'not this one')->create();
    $quote = Quote::factory()->for($this->guild)->withLine('Bo', 'i can fix it')->create();
    $quote->lines()->update(['person_id' => $bo->id]);
    jevSaysIntent('random_quote');

    dispatch_sync(new CaptureFromMention(mentionMessage('<@1000> a quote from <@50> please', [
        'mentions' => [['id' => '1000', 'username' => 'friendly-bot'], ['id' => '50', 'username' => 'bo']],
    ])));

    $this->discord->assertSent('777', 'i can fix it');
});

it('DMs the random quote when the server keeps random quotes private', function (): void {
    $this->guild->update(['private_random_quotes' => true]);
    Quote::factory()->for($this->guild)->withLine('Bo', 'i can fix it')->create();
    jevSaysIntent('random_quote');

    dispatch_sync(new CaptureFromMention(mentionMessage('<@1000> random quote pls')));

    expect($this->discord->sentMessages())->toBe([])
        ->and(FakeDiscordClient::content($this->discord->directMessages()[0]['message']))->toContain('i can fix it');
});

it('DMs help when the mention asks what the bot can do', function (): void {
    jevSaysIntent('help');

    dispatch_sync(new CaptureFromMention(mentionMessage('<@1000> what can you do?')));

    expect(Quote::query()->count())->toBe(0)
        ->and(FakeDiscordClient::content($this->discord->directMessages()[0]['message']))->toContain('/quote add');
});

it('saves a quote when Jev is unsure', function (): void {
    jevSaysIntent('random_quote', 0.5);

    dispatch_sync(new CaptureFromMention(mentionMessage('<@1000> Bo: random quote')));

    expect(Quote::query()->sole()->source)->toBe(QuoteSource::MentionFreetext);
});

it('saves a quote when Jev fails', function (): void {
    config()->set('ai.providers.openrouter.key', 'or-key');
    Classification::fake(fn (): never => throw new RuntimeException('OpenRouter is down'));

    dispatch_sync(new CaptureFromMention(mentionMessage('<@1000> Bo: hi')));

    expect(Quote::query()->sole()->source)->toBe(QuoteSource::MentionFreetext);
});

it('does not ask Jev without an OpenRouter key', function (): void {
    config()->set('ai.providers.openrouter.key', '');
    Classification::fake();

    dispatch_sync(new CaptureFromMention(mentionMessage('<@1000> Bo: hi')));

    Classification::assertNothingClassified();
    expect(Quote::query()->count())->toBe(1);
});

it('always saves the replied-to message, without asking Jev', function (): void {
    jevSaysIntent('help');

    dispatch_sync(new CaptureFromMention(mentionMessage('<@1000> what can you do', ['referenced_message' => P::message('999', 'i can fix it', '50')])));

    Classification::assertNothingClassified();
    expect(Quote::query()->sole()->source)->toBe(QuoteSource::ReplyMention);
});

it('picks the random quote from a person named in the text, not on Discord', function (string $text): void {
    $uncle = Person::createNamed('Uncle Rolf');
    PersonAlias::query()->create(['person_id' => $uncle->id, 'alias' => 'Rolle']);
    Quote::factory()->for($this->guild)->withLine('Ann', 'not this one')->create();
    $quote = Quote::factory()->for($this->guild)->withLine('Uncle Rolf', 'back in my day')->create();
    $quote->lines()->update(['person_id' => $uncle->id]);
    jevSaysIntent('random_quote');

    dispatch_sync(new CaptureFromMention(mentionMessage("<@1000> {$text}")));

    $this->discord->assertSent('777', 'back in my day');
})->with([
    'name' => 'a quote from Uncle Rolf please',
    'nickname, Swedish' => 'ge mig ett citat från rolle!',
    'by' => 'random quote by uncle rolf',
]);

it('falls back to anyone when the named person is unknown', function (): void {
    Quote::factory()->for($this->guild)->withLine('Ann', 'only one')->create();
    jevSaysIntent('random_quote');

    dispatch_sync(new CaptureFromMention(mentionMessage('<@1000> a quote from last summer')));

    $this->discord->assertSent('777', 'only one');
});
