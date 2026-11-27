<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LinkPersonRequest;
use App\Http\Requests\Admin\MergePeopleRequest;
use App\Http\Requests\Admin\StoreAliasRequest;
use App\Http\Resources\PersonAdminResource;
use App\Models\DiscordUser;
use App\Models\Person;
use App\Models\PersonAlias;
use App\Quotes\Actions\MergePeople;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Pentacore\Typefinder\Attributes\TypefinderPage;

/**
 * Admins tidy up people: nicknames, Discord links and duplicates.
 */
final class PeopleController extends Controller
{
    #[TypefinderPage(component: 'admin/People', props: [
        'people' => 'SimplePaginatedResourceCollection<'.PersonAdminResource::class.'>',
        'search' => 'string',
        'discord_users' => '{ id: number; name: string }[]',
        'all_people' => '{ id: number; display_name: string }[]',
    ])]
    public function index(Request $request): Response
    {
        $search = trim($request->string('q')->value());

        $people = Person::query()
            ->with(['aliases', 'discordUser'])
            ->withCount(['quoteLines as quote_count' => fn (Builder $lines) => $lines->select(DB::raw('count(distinct quote_id)'))])
            ->when($search !== '', function (Builder $query) use ($search): void {
                // % and _ typed into the box are literal, not wildcards.
                $pattern = '%'.addcslashes($search, '\\%_').'%';

                $query->where(fn (Builder $matches) => $matches
                    ->whereRaw('display_name ilike ?', [$pattern])
                    ->orWhereHas('aliases', fn (Builder $aliases) => $aliases->whereRaw('alias ilike ?', [$pattern])))
                    ->orderByRaw('similarity(lower(display_name), lower(?)) desc', [$search]);
            })
            ->orderBy('display_name')
            ->simplePaginate(30)
            ->withQueryString();

        return Inertia::render('admin/People', [
            'people' => Inertia::scroll(PersonAdminResource::collection($people)),
            'search' => $search,
            'discord_users' => DiscordUser::query()->orderBy('username')->get()
                ->map(fn (DiscordUser $user): array => ['id' => $user->id, 'name' => $user->display_name.' (@'.$user->username.')'])->values()->all(),
            'all_people' => Person::query()->orderBy('display_name')->get(['id', 'display_name'])
                ->map(fn (Person $person): array => ['id' => $person->id, 'display_name' => $person->display_name])->values()->all(),
        ]);
    }

    public function storeAlias(StoreAliasRequest $request, Person $person): RedirectResponse
    {
        $person->aliases()->create(['alias' => trim($request->string('alias')->value())]);

        return back();
    }

    public function destroyAlias(Request $request, Person $person, PersonAlias $alias): RedirectResponse
    {
        abort_unless($request->user()?->can('admin') && $alias->person_id === $person->id, 403);

        $alias->delete();

        return back();
    }

    public function link(LinkPersonRequest $request, Person $person): RedirectResponse
    {
        $person->update(['discord_user_id' => $request->filled('discord_user_id') ? $request->integer('discord_user_id') : null]);

        return back();
    }

    public function merge(MergePeopleRequest $request, Person $person, MergePeople $mergePeople): RedirectResponse
    {
        $into = Person::query()->findOrFail($request->integer('into_person_id'));
        $mergePeople->handle($person, $into);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':from merged into :into.', ['from' => $person->display_name, 'into' => $into->display_name])]);

        return back();
    }
}
