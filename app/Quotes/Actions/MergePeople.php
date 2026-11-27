<?php

declare(strict_types=1);

namespace App\Quotes\Actions;

use App\Models\Person;
use App\Models\PersonAlias;
use App\Models\QuoteLine;
use Illuminate\Support\Facades\DB;

/**
 * Merges a duplicate person into another: their quote lines and aliases
 * move over, their name becomes an alias, a Discord link moves if the
 * target has none, and the duplicate is deleted.
 */
final readonly class MergePeople
{
    public function handle(Person $from, Person $into): Person
    {
        return DB::transaction(function () use ($from, $into): Person {
            QuoteLine::query()->where('person_id', $from->id)->update(['person_id' => $into->id]);

            $taken = array_map(mb_strtolower(...), $this->names($into));

            foreach ($this->names($from) as $name) {
                if (! in_array(mb_strtolower($name), $taken, true)) {
                    $taken[] = mb_strtolower($name);
                    PersonAlias::query()->updateOrCreate(['alias' => $name], ['person_id' => $into->id]);
                }
            }

            $discordUserId = $into->discord_user_id ?? $from->discord_user_id;
            $from->aliases()->delete();
            $from->delete();
            $into->update(['discord_user_id' => $discordUserId]);

            return $into;
        });
    }

    /**
     * A person's display name and nicknames.
     *
     * @return list<string>
     */
    private function names(Person $person): array
    {
        return [$person->display_name, ...$person->aliases()->get()->map(fn (PersonAlias $alias): string => $alias->alias)->all()];
    }
}
