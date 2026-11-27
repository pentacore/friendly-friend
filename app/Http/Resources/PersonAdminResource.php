<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Person;
use App\Models\PersonAlias;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * A person on the admin people page. Load `aliases`, `discordUser` and
 * `quote_count` (withCount).
 */
final class PersonAdminResource extends JsonResource
{
    public function __construct(private readonly Person $person)
    {
        parent::__construct($person);
    }

    /**
     * @return array{
     *     id: int,
     *     slug: string,
     *     display_name: string,
     *     aliases: list<array{id: int, alias: string}>,
     *     discord_user: array{id: int, name: string, avatar_url: string}|null,
     *     quote_count: int,
     * }
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $person = $this->person;

        return [
            'id' => $person->id,
            'slug' => $person->slug,
            'display_name' => $person->display_name,
            'aliases' => array_values($person->aliases->map(fn (PersonAlias $alias): array => ['id' => $alias->id, 'alias' => $alias->alias])->all()),
            'discord_user' => $person->discordUser !== null
                ? ['id' => $person->discordUser->id, 'name' => $person->discordUser->display_name, 'avatar_url' => $person->discordUser->avatarUrl()]
                : null,
            'quote_count' => $person->quote_count ?? 0,
        ];
    }
}
