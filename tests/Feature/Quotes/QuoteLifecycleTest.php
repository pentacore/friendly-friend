<?php

declare(strict_types=1);

use App\Enums\QuoteSource;
use App\Enums\QuoteStatus;
use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\Person;
use App\Models\Quote;
use App\Quotes\Actions\CancelDraft;
use App\Quotes\Actions\ConfirmQuote;
use App\Quotes\Actions\DraftQuote;
use App\Quotes\Actions\UpdateDraft;
use App\Quotes\Data\NormalizedLine;
use App\Quotes\Data\NormalizedQuote;
use App\Quotes\Data\SourceMessage;
use App\Quotes\Exceptions\AlreadyQuotedException;

function draftFor(Guild $guild, DiscordUser $submitter, NormalizedQuote $content, ?SourceMessage $source = null): Quote
{
    return app(DraftQuote::class)->handle($guild, $submitter, QuoteSource::SlashCommand, 'raw text', $content, $source);
}

beforeEach(function (): void {
    $this->guild = Guild::factory()->create();
    $this->submitter = DiscordUser::factory()->create();
    $this->speaker = DiscordUser::factory()->create(['discord_id' => '42', 'global_name' => 'Martin']);
});

it('drafts a quote with resolved and new speakers', function (): void {
    $quote = draftFor($this->guild, $this->submitter, new NormalizedQuote([
        new NormalizedLine('Marre', 'I never said that', '42'),
        new NormalizedLine('Uncle Bo', 'You did.'),
    ], context: 'at dinner'));

    expect($quote->status)->toBe(QuoteStatus::Draft)
        ->and($quote->guild_id)->toBe($this->guild->id)
        ->and($quote->context)->toBe('at dinner')
        ->and($quote->lines)->toHaveCount(2)
        ->and($quote->lines[0]->speaker_discord_user_id)->toBe($this->speaker->id)
        ->and($quote->lines[1]->isNewPerson())->toBeTrue()
        ->and(Person::query()->count())->toBe(0);
});

it('replaces the content of a draft', function (): void {
    $quote = draftFor($this->guild, $this->submitter, new NormalizedQuote([new NormalizedLine('A', 'one')]));

    app(UpdateDraft::class)->handle($quote, new NormalizedQuote([
        new NormalizedLine('B', 'two'),
        new NormalizedLine('C', 'three'),
    ], context: 'edited'));

    $quote->refresh();
    expect($quote->lines->pluck('text')->all())->toBe(['two', 'three'])
        ->and($quote->context)->toBe('edited');
});

it('saves a draft: creates people, publishes and records a revision', function (): void {
    $quote = draftFor($this->guild, $this->submitter, new NormalizedQuote([
        new NormalizedLine('Marre', 'I never said that', '42'),
        new NormalizedLine('Uncle Bo', 'You did.'),
    ]));

    app(ConfirmQuote::class)->handle($quote, $this->submitter);

    $quote->refresh();
    expect($quote->status)->toBe(QuoteStatus::Published)
        ->and($quote->published_at)->not->toBeNull()
        ->and($quote->lines->every(fn ($line): bool => $line->person_id !== null))->toBeTrue()
        ->and(Person::query()->where('discord_user_id', $this->speaker->id)->sole()->display_name)->toBe('Martin')
        ->and(Person::query()->where('display_name', 'Uncle Bo')->sole()->discord_user_id)->toBeNull()
        ->and($quote->revisions()->sole()->snapshot['lines'][1]['speaker'])->toBe('Uncle Bo');
});

it('reuses a person created in the meantime instead of duplicating', function (): void {
    $quote = draftFor($this->guild, $this->submitter, new NormalizedQuote([new NormalizedLine('Uncle Bo', 'Hi')]));
    $bo = Person::createNamed('Uncle Bo');

    app(ConfirmQuote::class)->handle($quote, $this->submitter);

    expect($quote->refresh()->lines[0]->person_id)->toBe($bo->id)
        ->and(Person::query()->count())->toBe(1);
});

it('cancels a draft', function (): void {
    $quote = draftFor($this->guild, $this->submitter, new NormalizedQuote([new NormalizedLine('A', 'one')]));

    app(CancelDraft::class)->handle($quote);

    expect(Quote::query()->count())->toBe(0);
});

it('refuses to quote a message that was already saved', function (): void {
    $source = new SourceMessage('999', '777', 'https://discord.com/channels/1/777/999');
    $quote = draftFor($this->guild, $this->submitter, new NormalizedQuote([new NormalizedLine('A', 'one')]), $source);
    app(ConfirmQuote::class)->handle($quote, $this->submitter);

    draftFor($this->guild, $this->submitter, new NormalizedQuote([new NormalizedLine('A', 'one')]), $source);
})->throws(AlreadyQuotedException::class);

it('replaces an unsaved draft of the same message', function (): void {
    $source = new SourceMessage('999', '777', null);
    draftFor($this->guild, $this->submitter, new NormalizedQuote([new NormalizedLine('A', 'one')]), $source);

    draftFor($this->guild, DiscordUser::factory()->create(), new NormalizedQuote([new NormalizedLine('A', 'two')]), $source);

    expect(Quote::query()->sole()->lines[0]->text)->toBe('two');
});
