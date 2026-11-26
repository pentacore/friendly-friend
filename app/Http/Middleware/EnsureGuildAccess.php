<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Access\GuildAccess;
use App\Enums\AccessDeniedReason;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-checks guild membership and roles on every request, using the member
 * row that the bot and the scheduled sync keep fresh. Someone who left the
 * server or lost their role is logged out on their next request.
 */
final readonly class EnsureGuildAccess
{
    public function __construct(private GuildAccess $access) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $this->access->allows($user->discordMember)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login')->with('error', AccessDeniedReason::AccessRevoked->value);
        }

        return $next($request);
    }
}
