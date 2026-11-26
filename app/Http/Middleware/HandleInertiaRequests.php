<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Access\GuildAccess;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Override;

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
     *     auth: array{user: array{id: int, discord_id: string, name: string, avatar_url: string, is_admin: bool}|null},
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
     * @return array{id: int, discord_id: string, name: string, avatar_url: string, is_admin: bool}|null
     */
    private function currentUser(Request $request): ?array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $member = $user->discordMember;

        return [
            'id' => $user->id,
            'discord_id' => $member->discord_id,
            'name' => $member->display_name,
            'avatar_url' => $member->avatarUrl(),
            'is_admin' => app(GuildAccess::class)->isAdmin($member),
        ];
    }
}
