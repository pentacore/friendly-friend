<?php

declare(strict_types=1);

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\GuildMember;
use App\Models\Person;
use App\Models\PersonAlias;
use App\Quotes\Actions\ResolvePerson;
use App\Quotes\Data\NormalizedLine;
use App\Quotes\Data\ResolvedSpeaker;
use Laravel\Ai\Classification;
use Laravel\Ai\Responses\Data\ChoiceAnswer;

function resolveSpeaker(Guild $guild, string $name, ?string $discordId = null): ResolvedSpeaker
{
    return app(ResolvePerson::class)->handle($guild, new NormalizedLine($name, 'text', $discordId));
}

beforeEach(function (): void {
    $this->guild = Guild::factory()->create();
});

it('resolves a known discord user to their person', function (): void {
    $user = DiscordUser::factory()->create(['discord_id' => '42']);
    $person = Person::factory()->forDiscordUser($user)->create();

    $speaker = resolveSpeaker($this->guild, 'whatever', '42');

    expect($speaker->personId)->toBe($person->id)
        ->and($speaker->discordUserId)->toBe($user->id)
        ->and($speaker->name)->toBe($person->display_name);
});

it('links a discord user who has no person yet', function (): void {
    $user = DiscordUser::factory()->create(['discord_id' => '42', 'global_name' => 'Martin']);

    $speaker = resolveSpeaker($this->guild, 'Marre', '42');

    expect($speaker->personId)->toBeNull()
        ->and($speaker->discordUserId)->toBe($user->id)
        ->and($speaker->name)->toBe('Martin')
        ->and($speaker->isNew())->toBeFalse();
});

it('matches aliases case-insensitively', function (): void {
    $person = Person::factory()->create(['display_name' => 'Martin']);
    PersonAlias::factory()->for($person)->create(['alias' => 'Marre']);

    expect(resolveSpeaker($this->guild, 'MARRE')->personId)->toBe($person->id);
});

it('matches a person by display name', function (): void {
    $person = Person::factory()->create(['display_name' => 'Uncle Bo']);

    expect(resolveSpeaker($this->guild, 'uncle bo')->personId)->toBe($person->id);
});

it('matches guild members by nickname, global name or username', function (string $name): void {
    $user = DiscordUser::factory()->create(['username' => 'penta', 'global_name' => 'Martin C']);
    GuildMember::factory()->for($this->guild)->for($user)->create(['nick' => 'Marre']);

    expect(resolveSpeaker($this->guild, $name)->discordUserId)->toBe($user->id);
})->with(['Marre', 'martin c', 'PENTA']);

it('does not match members of other guilds by name', function (): void {
    $user = DiscordUser::factory()->create(['username' => 'penta']);
    GuildMember::factory()->for(Guild::factory()->create())->for($user)->create();

    expect(resolveSpeaker($this->guild, 'penta')->discordUserId)->toBeNull();
});

it('fuzzy matches close spellings', function (): void {
    $person = Person::factory()->create(['display_name' => 'Fredrik']);

    expect(resolveSpeaker($this->guild, 'Fredrick')->personId)->toBe($person->id);
});

it('proposes a new person when nothing matches', function (): void {
    Person::factory()->create(['display_name' => 'Fredrik']);

    $speaker = resolveSpeaker($this->guild, "Martin's uncle");

    expect($speaker->isNew())->toBeTrue()
        ->and($speaker->name)->toBe("Martin's uncle");
});

it('keeps the closest fuzzy match when Jev is not configured', function (): void {
    config()->set('ai.providers.openrouter.key', '');
    Classification::fake();
    $fredrik = Person::factory()->create(['display_name' => 'Fredrik']);
    Person::factory()->create(['display_name' => 'Fredrika']);

    expect(resolveSpeaker($this->guild, 'Fredrick')->personId)->toBe($fredrik->id);
    Classification::assertNothingClassified();
});

it('asks Jev through OpenRouter to pick between several fuzzy matches', function (): void {
    config()->set('ai.providers.openrouter.key', 'or-key');
    Person::factory()->create(['display_name' => 'Fredrik']);
    $fredrika = Person::factory()->create(['display_name' => 'Fredrika']);
    PersonAlias::factory()->for($fredrika)->create(['alias' => 'Fia']);
    Classification::fake(fn (): array => ['person' => new ChoiceAnswer('Fredrika', ['Fredrika' => 0.8], 0.8)]);

    expect(resolveSpeaker($this->guild, 'Fredrick')->personId)->toBe($fredrika->id);
    Classification::assertClassified(fn ($prompt): bool => $prompt->provider->name() === 'openrouter'
        && array_keys($prompt->questions['person']->options) === ['Fredrik', 'Fredrika', 'someone else']
        && str_contains((string) json_encode($prompt->questions['person']->options), 'Fia'));
});

it('proposes a new person when Jev says none of the matches fit', function (): void {
    config()->set('ai.providers.openrouter.key', 'or-key');
    Person::factory()->create(['display_name' => 'Fredrik']);
    Person::factory()->create(['display_name' => 'Fredrika']);
    Classification::fake(fn (): array => ['person' => new ChoiceAnswer('someone else', ['someone else' => 0.7], 0.7)]);

    $speaker = resolveSpeaker($this->guild, 'Fredrick');

    expect($speaker->personId)->toBeNull()
        ->and($speaker->name)->toBe('Fredrick');
});

it('keeps the closest fuzzy match when Jev fails', function (): void {
    config()->set('ai.providers.openrouter.key', 'or-key');
    $fredrik = Person::factory()->create(['display_name' => 'Fredrik']);
    Person::factory()->create(['display_name' => 'Fredrika']);
    Classification::fake(fn (): never => throw new RuntimeException('OpenRouter is down'));

    expect(resolveSpeaker($this->guild, 'Fredrick')->personId)->toBe($fredrik->id);
});

it('does not ask Jev when only one person matches', function (): void {
    config()->set('ai.providers.openrouter.key', 'or-key');
    Classification::fake();
    $fredrik = Person::factory()->create(['display_name' => 'Fredrik']);

    expect(resolveSpeaker($this->guild, 'Fredrick')->personId)->toBe($fredrik->id);
    Classification::assertNothingClassified();
});
