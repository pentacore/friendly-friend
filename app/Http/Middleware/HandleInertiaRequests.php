<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Access\GuildAccess;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Override;

/**
 * Inertia root view and shared props (app name, the logged-in Discord user, sidebar state).
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    #[Override]
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     *
     * @typefinder-return array{
     *     name: string,
     *     auth: array{user: array{id: int, discord_id: string, name: string, avatar_url: string, is_admin: bool, is_owner: bool}|null},
     *     sidebarOpen: bool,
     * }
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $this->currentUser($request),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The logged-in user as the frontend sees them: Discord identity only.
     *
     * @return array{id: int, discord_id: string, name: string, avatar_url: string, is_admin: bool, is_owner: bool}|null
     */
    private function currentUser(Request $request): ?array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $discordUser = $user->discordUser;
        $access = app(GuildAccess::class);

        return [
            'id' => $user->id,
            'discord_id' => $discordUser->discord_id,
            'name' => $discordUser->display_name,
            'avatar_url' => $discordUser->avatarUrl(),
            'is_admin' => $access->isAdmin($discordUser),
            'is_owner' => $access->isOwner($discordUser),
        ];
    }
}
