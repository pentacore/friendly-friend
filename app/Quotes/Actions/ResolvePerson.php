<?php

declare(strict_types=1);

namespace App\Quotes\Actions;

use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\GuildMember;
use App\Models\Person;
use App\Models\PersonAlias;
use App\Quotes\Data\NormalizedLine;
use App\Quotes\Data\ResolvedSpeaker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Works out who said a line, in order: a known Discord user (message author
 * or mention), an alias, a person's name, a member of the quote's guild by
 * nickname / display name / username, a fuzzy match (pg_trgm) on people and
 * aliases. Otherwise it's a new person by that name.
 */
final readonly class ResolvePerson
{
    /** Fredrik/Fredrick ≈ 0.55. Matches are shown in the preview, so a wrong guess is caught. */
    private const float FUZZY_THRESHOLD = 0.5;

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

        $person = $this->fuzzyMatch($lower);

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

    private function fuzzyMatch(string $lower): ?Person
    {
        $match = DB::query()
            ->fromSub(
                DB::table('people')
                    ->selectRaw('id as person_id, similarity(lower(display_name), ?) as score', [$lower])
                    ->unionAll(DB::table('person_aliases')
                        ->selectRaw('person_id, similarity(lower(alias), ?) as score', [$lower])),
                'candidates',
            )
            ->where('score', '>=', self::FUZZY_THRESHOLD)
            ->orderByDesc('score')
            ->first();

        $personId = is_object($match) && is_int($match->person_id ?? null) ? $match->person_id : null;

        return $personId !== null ? Person::query()->find($personId) : null;
    }
}
