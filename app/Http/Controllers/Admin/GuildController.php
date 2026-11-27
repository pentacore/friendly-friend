<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Access\Actions\UpdateGuildSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateGuildRequest;
use App\Http\Resources\GuildResource;
use App\Models\Guild;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Pentacore\Typefinder\Attributes\TypefinderPage;

/**
 * Owners-only server management (/admin/guilds).
 */
final class GuildController extends Controller
{
    #[TypefinderPage(component: 'admin/Guilds', props: ['guilds' => GuildResource::class.'[]'])]
    public function index(Request $request): Response
    {
        $guilds = Guild::query()
            ->orderByDesc('enabled')
            ->orderByDesc('bot_present')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/Guilds', [
            'guilds' => GuildResource::collection($guilds)->resolve($request),
        ]);
    }

    public function update(UpdateGuildRequest $request, Guild $guild, UpdateGuildSettings $updateSettings): RedirectResponse
    {
        $updateSettings->handle(
            $guild,
            enabled: $request->boolean('enabled'),
            allowAllMembers: $request->boolean('allow_all_members'),
            allowedRoleIds: $this->ids($request->array('allowed_role_ids')),
            adminRoleIds: $this->ids($request->array('admin_role_ids')),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name saved.', ['name' => $guild->name])]);

        return to_route('admin.guilds.index');
    }

    /**
     * @param  array<mixed>  $values
     * @return list<string>
     */
    private function ids(array $values): array
    {
        return array_values(array_filter($values, is_string(...)));
    }
}
