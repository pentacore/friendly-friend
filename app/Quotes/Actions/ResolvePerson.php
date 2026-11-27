<?php

declare(strict_types=1);

namespace App\Quotes\Actions;

use App\Ai\Jev;
use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\GuildMember;
use App\Models\Person;
use App\Models\PersonAlias;
use App\Quotes\Data\NormalizedLine;
use App\Quotes\Data\ResolvedSpeaker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Classification\Choice;
use Laravel\Ai\Responses\Data\ChoiceAnswer;
use Throwable;

/**
 * Works out who said a line, in order: a known Discord user (message author
 * or mention), an alias, a person's name, a member of the quote's guild by
 * nickname / display name / username, a fuzzy match (pg_trgm) on people and
 * aliases. Otherwise it's a new person by that name. When several people
 * match fuzzily, Jev (through OpenRouter) picks one, or none; without Jev the
 * closest match wins.
 */
final readonly class ResolvePerson
{
    /** Fredrik/Fredrick ≈ 0.55. Matches are shown in the preview, so a wrong guess is caught. */
    private const float FUZZY_THRESHOLD = 0.5;

    /** How many fuzzy matches Jev chooses between. */
    private const int MAX_CANDIDATES = 5;

    private const string SOMEONE_ELSE = 'someone else';

    public function __construct(private Jev $jev) {}

    public function handle(Guild $guild, NormalizedLine $line): ResolvedSpeaker
    {
        $name = trim($line->speakerName);

        if ($line->speakerDiscordId !== null) {
            $user = DiscordUser::query()->where('discord_id', $line->speakerDiscordId)->first();

            if ($user !== null) {
                return $this->forDiscordUser($user);
            }
        }

        $lower = mb_strtolower($name);

        $alias = PersonAlias::query()->whereRaw('lower(alias) = ?', [$lower])->with('person')->first();
        $person = $alias !== null
            ? $alias->person
            : Person::query()->whereRaw('lower(display_name) = ?', [$lower])->first();

        if ($person !== null) {
            return $this->forPerson($person);
        }

        $member = $this->guildMemberNamed($guild, $lower);

        if ($member instanceof GuildMember) {
            return $this->forDiscordUser($member->discordUser);
        }

        $person = $this->fuzzyMatch($guild, $line, $lower);

        return $person instanceof Person ? $this->forPerson($person) : new ResolvedSpeaker($name);
    }

    private function forDiscordUser(DiscordUser $user): ResolvedSpeaker
    {
        $person = Person::query()->where('discord_user_id', $user->id)->first();

        return new ResolvedSpeaker($person->display_name ?? $user->display_name, $person?->id, $user->id);
    }

    private function forPerson(Person $person): ResolvedSpeaker
    {
        return new ResolvedSpeaker($person->display_name, $person->id, $person->discord_user_id);
    }

    private function guildMemberNamed(Guild $guild, string $lower): ?GuildMember
    {
        return GuildMember::query()
            ->whereBelongsTo($guild)
            ->where(fn (Builder $query) => $query
                ->whereRaw('lower(nick) = ?', [$lower])
                ->orWhereHas('discordUser', fn (Builder $user) => $user
                    ->whereRaw('lower(global_name) = ?', [$lower])
                    ->orWhereRaw('lower(username) = ?', [$lower])))
            ->with('discordUser')
            ->first();
    }

    private function fuzzyMatch(Guild $guild, NormalizedLine $line, string $lower): ?Person
    {
        $candidates = $this->fuzzyCandidates($lower);

        if ($candidates->count() < 2 || ! $this->jev->isAvailable()) {
            return $candidates->first();
        }

        try {
            return $this->askJev($guild, $line, $candidates);
        } catch (Throwable $e) {
            Log::warning('Jev could not pick a person; using the closest match.', ['exception' => $e->getMessage()]);

            return $candidates->first();
        }
    }

    /**
     * People whose name or an alias is close to the name, closest first.
     *
     * @return Collection<int, Person>
     */
    private function fuzzyCandidates(string $lower): Collection
    {
        $personIds = DB::query()
            ->fromSub(
                DB::table('people')
                    ->selectRaw('id as person_id, similarity(lower(display_name), ?) as score', [$lower])
                    ->unionAll(DB::table('person_aliases')
                        ->selectRaw('person_id, similarity(lower(alias), ?) as score', [$lower])),
                'candidates',
            )
            ->where('score', '>=', self::FUZZY_THRESHOLD)
            ->groupBy('person_id')
            ->orderByRaw('max(score) desc')
            ->limit(self::MAX_CANDIDATES)
            ->pluck('person_id')
            ->map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)
            ->all();

        $people = Person::query()->whereKey($personIds)->with(['aliases', 'discordUser'])->get()->keyBy('id');

        return new Collection(array_values(array_filter(array_map(fn (int $id): ?Person => $people->get($id), $personIds))));
    }

    /**
     * @param  Collection<int, Person>  $candidates
     */
    private function askJev(Guild $guild, NormalizedLine $line, Collection $candidates): ?Person
    {
        $memberIds = GuildMember::query()->whereBelongsTo($guild)->where('in_guild', true)
            ->whereIn('discord_user_id', $candidates->pluck('discord_user_id')->filter())
            ->pluck('discord_user_id')
            ->all();

        $options = [];
        $byOption = [];

        foreach ($candidates as $person) {
            $option = array_key_exists($person->display_name, $byOption) ? "{$person->display_name} (#{$person->id})" : $person->display_name;
            $byOption[$option] = $person;
            $hints = array_filter([
                $person->aliases->isNotEmpty() ? 'also called '.$person->aliases->pluck('alias')->implode(', ') : null,
                in_array($person->discord_user_id, $memberIds, true) ? 'a member of this Discord server' : null,
            ]);
            $options[$option] = $hints !== [] ? implode('; ', $hints) : null;
        }

        $options[self::SOMEONE_ELSE] = 'None of these: a different person who has not been quoted before.';

        $answer = $this->jev->ask(
            "A quote's speaker is written as \"{$line->speakerName}\". They said: \"{$line->text}\"",
            'person',
            new Choice('Which known person is this speaker? Names may be misspelled or nicknames.', $options),
        );

        if ($answer instanceof ChoiceAnswer && $answer->choice === self::SOMEONE_ELSE) {
            return null;
        }

        return $answer instanceof ChoiceAnswer ? ($byOption[$answer->choice] ?? $candidates->first()) : $candidates->first();
    }
}
