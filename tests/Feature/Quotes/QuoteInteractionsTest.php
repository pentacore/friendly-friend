<?php

declare(strict_types=1);

use App\Ai\PlainQuoteNormalizer;
use App\Ai\QuoteNormalizer;
use App\Discord\DiscordClient;
use App\Discord\Interactions\CustomId;
use App\Discord\Interactions\DraftEditForm;
use App\Discord\Testing\FakeDiscordClient;
use App\Enums\QuoteSource;
use App\Enums\QuoteStatus;
use App\Enums\SaidAtPrecision;
use App\Models\Guild;
use App\Models\Person;
use App\Models\Quote;
use App\Models\QuoteImport;
use App\Quotes\Discord\QuoteInteractions;
use App\Quotes\Jobs\ImportChannelQuotes;
use Illuminate\Support\Facades\Queue;
use Tests\Support\DiscordPayloads as P;

beforeEach(function (): void {
    app()->bind(QuoteNormalizer::class, PlainQuoteNormalizer::class);
    app()->instance(DiscordClient::class, $this->discord = new FakeDiscordClient);
    $this->guild = Guild::factory()->create(['discord_id' => '555', 'quotes_channel_id' => '888']);
});

function interact(array $payload): void
{
    app(QuoteInteractions::class)->handle($payload);
}

function responseText(FakeDiscordClient $discord): string
{
    return FakeDiscordClient::content($discord->lastResponse());
}

it('drafts /quote add and replies with a private preview', function (): void {
    interact(P::slash('add', [
        ['name' => 'text', 'type' => 3, 'value' => 'cheese is a vegetable'],
        ['name' => 'who_name', 'type' => 3, 'value' => "Martin's uncle"],
        ['name' => 'context', 'type' => 3, 'value' => 'at the barbecue'],
        ['name' => 'when', 'type' => 3, 'value' => 'yesterday'],
    ]));

    $quote = Quote::query()->with('lines')->sole();
    expect($quote->status)->toBe(QuoteStatus::Draft)
        ->and($quote->source)->toBe(QuoteSource::SlashCommand)
        ->and($quote->lines[0]->speaker_name)->toBe("Martin's uncle")
        ->and($quote->context)->toBe('at the barbecue')
        ->and($quote->said_at_precision)->toBe(SaidAtPrecision::Day)
        ->and(responseText($this->discord))->toContain("Martin's uncle *(new person)*")
        ->and($this->discord->lastResponse()['components'][0]['components'])->toHaveCount(3)
        ->and(DraftEditForm::cached($quote->id))->not->toBeNull();
});

it('uses a Discord user picked as "who"', function (): void {
    interact(P::slash('add',
        [['name' => 'text', 'type' => 3, 'value' => 'I can fix it'], ['name' => 'who', 'type' => 6, 'value' => '50']],
        ['users' => ['50' => ['id' => '50', 'username' => 'bo', 'global_name' => 'Bo', 'avatar' => null]]],
    ));

    $line = Quote::query()->sole()->lines()->sole();
    expect($line->speaker_name)->toBe('Bo')
        ->and($line->speaker_discord_user_id)->not->toBeNull();
});

it('refuses servers that are not enabled', function (): void {
    $this->guild->update(['enabled' => false]);

    interact(P::slash('add', [['name' => 'text', 'type' => 3, 'value' => 'x']]));

    expect(responseText($this->discord))->toContain("aren't enabled")
        ->and(Quote::query()->count())->toBe(0);
});

it('refuses members without access', function (): void {
    interact(P::slash('add', [['name' => 'text', 'type' => 3, 'value' => 'x']], member: P::member(roles: ['5'])));

    expect(responseText($this->discord))->toContain("don't have access")
        ->and(Quote::query()->count())->toBe(0);
});

it('drafts the target of "Save quote" by its author at the exact time', function (): void {
    interact(P::saveQuoteMenu(P::message('999', 'blame <@42> for this', '50', [
        'mentions' => [['id' => '42', 'username' => 'martin', 'global_name' => 'Martin']],
    ])));

    $quote = Quote::query()->with('lines')->sole();
    expect($quote->source)->toBe(QuoteSource::ContextMenu)
        ->and($quote->source_message_id)->toBe('999')
        ->and($quote->source_message_url)->toBe('https://discord.com/channels/555/777/999')
        ->and($quote->said_at_precision)->toBe(SaidAtPrecision::Exact)
        ->and($quote->lines[0]->speaker_name)->toBe('Bo')
        ->and($quote->lines[0]->text)->toBe('blame @Martin for this');
});

it('refuses to quote bots, empty messages and already saved messages', function (array $message, string $expected): void {
    Quote::factory()->for($this->guild)->create(['source_message_id' => 'saved']);

    interact(P::saveQuoteMenu($message));

    expect(responseText($this->discord))->toContain($expected);
})->with([
    'bot' => [P::message('1', 'beep', '9', ['author' => ['id' => '9', 'username' => 'b', 'bot' => true]]), "can't quote bots"],
    'empty' => [P::message('2', '   '), 'no text'],
    'already saved' => [P::message('saved'), 'already saved'],
]);

it('saves a draft: publishes, creates people and posts to the quotes channel', function (): void {
    interact(P::saveQuoteMenu(P::message()));
    $quote = Quote::query()->sole();

    interact(P::button(CustomId::for('save', $quote->id)));

    expect($quote->refresh()->status)->toBe(QuoteStatus::Published)
        ->and(Person::query()->where('display_name', 'Bo')->exists())->toBeTrue()
        ->and(responseText($this->discord))->toContain('Saved')
        ->and(DraftEditForm::cached($quote->id))->toBeNull();
    $this->discord->assertSent('888', 'philosophical');
});

it('does not post when the server has no quotes channel', function (): void {
    $this->guild->update(['quotes_channel_id' => null]);
    interact(P::saveQuoteMenu(P::message()));

    interact(P::button(CustomId::for('save', Quote::query()->sole()->id)));

    expect($this->discord->sentMessages())->toBe([]);
});

it('cancels a draft', function (): void {
    interact(P::saveQuoteMenu(P::message()));

    interact(P::button(CustomId::for('cancel', Quote::query()->sole()->id)));

    expect(Quote::query()->count())->toBe(0)
        ->and(responseText($this->discord))->toContain('Cancelled');
});

it('only lets the submitter use the buttons, also from DMs', function (): void {
    interact(P::saveQuoteMenu(P::message()));
    $quote = Quote::query()->sole();

    interact(P::button(CustomId::for('save', $quote->id), userId: '77'));
    expect($quote->refresh()->status)->toBe(QuoteStatus::Draft)
        ->and(responseText($this->discord))->toContain('Only the person');

    interact(P::button(CustomId::for('save', $quote->id), userId: '42', inDm: true));
    expect($quote->refresh()->status)->toBe(QuoteStatus::Published);
});

it('says when a draft has expired', function (): void {
    interact(P::button(CustomId::for('save', 424242)));

    expect(responseText($this->discord))->toContain('expired');
});

it('applies the edit form to the draft', function (): void {
    interact(P::saveQuoteMenu(P::message()));
    $quote = Quote::query()->sole();

    interact(P::modal(CustomId::for('modal', $quote->id), [
        'lines' => "Bo: I'm not drunk\nMartin: you are",
        'context' => 'at 3am',
        'when' => '',
    ]));

    $quote->refresh()->load('lines');
    expect($quote->lines->pluck('text')->all())->toBe(["I'm not drunk", 'you are'])
        ->and($quote->context)->toBe('at 3am')
        ->and(responseText($this->discord))->toContain('Updated');
});

it('posts a random saved quote, optionally by one person', function (): void {
    interact(P::slash('random'));
    expect(responseText($this->discord))->toContain('No quotes yet');

    interact(P::saveQuoteMenu(P::message()));
    interact(P::button(CustomId::for('save', Quote::query()->sole()->id)));

    interact(P::slash('random', [['name' => 'person', 'type' => 6, 'value' => '50']]));
    expect(responseText($this->discord))->toContain('philosophical');

    interact(P::slash('random', [['name' => 'person', 'type' => 6, 'value' => '77']]));
    expect(responseText($this->discord))->toContain('No quotes yet');
});

it('lets only admins start a channel import', function (): void {
    Queue::fake();

    interact(P::slash('import', [['name' => 'channel', 'type' => 7, 'value' => '321']]));
    expect(responseText($this->discord))->toContain('Only admins');

    interact(P::slash('import', [['name' => 'channel', 'type' => 7, 'value' => '321']], member: P::member(roles: ['900'])));
    expect(QuoteImport::query()->sole()->channel_id)->toBe('321')
        ->and(responseText($this->discord))->toContain('Scanning');
    Queue::assertPushed(ImportChannelQuotes::class);
});

it('round-trips a single-speaker quote through the edit form', function (): void {
    interact(P::saveQuoteMenu(P::message()));
    $quote = Quote::query()->sole();

    $prefill = DraftEditForm::cached($quote->id)['components'][0]['components'][0]['value'];
    expect($prefill)->toBe('"im not drunk im philosophical" - Bo');

    interact(P::modal(CustomId::for('modal', $quote->id), ['lines' => '"I\'m not drunk, I\'m philosophical" - Bo', 'context' => '', 'when' => '']));

    $line = $quote->refresh()->lines()->sole();
    expect($line->speaker_name)->toBe('Bo')
        ->and($line->text)->toBe("I'm not drunk, I'm philosophical");
});
