<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Access\Actions\LoginWithDiscord;
use App\Access\Exceptions\AccessDeniedException;
use App\Enums\AccessDeniedReason;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use LogicException;
use Pentacore\Typefinder\Attributes\TypefinderPage;
use SocialiteProviders\Discord\Provider as DiscordProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

final class DiscordAuthController extends Controller
{
    #[TypefinderPage(component: 'auth/Login', props: ['error' => AccessDeniedReason::class.' | null'])]
    public function login(Request $request): Response
    {
        $error = $request->session()->get('error');

        return Inertia::render('auth/Login', [
            'error' => is_string($error) ? AccessDeniedReason::tryFrom($error) : null,
        ]);
    }

    public function redirect(): SymfonyRedirectResponse
    {
        // Only what access checks need: no email.
        return $this->discordProvider()
            ->setScopes(['identify', 'guilds', 'guilds.members.read'])
            ->redirect();
    }

    public function callback(Request $request, LoginWithDiscord $login): RedirectResponse
    {
        try {
            $discordUser = Socialite::driver('discord')->user();
        } catch (Throwable $e) {
            Log::warning('Discord OAuth callback failed', ['exception' => $e->getMessage()]);

            return $this->denied(AccessDeniedReason::OauthFailed);
        }

        if (! $discordUser instanceof SocialiteUser) {
            return $this->denied(AccessDeniedReason::OauthFailed);
        }

        try {
            $user = $login->handle($discordUser);
        } catch (AccessDeniedException $e) {
            return $this->denied($e->reason);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home');
    }

    private function denied(AccessDeniedReason $reason): RedirectResponse
    {
        return to_route('login')->with('error', $reason->value);
    }

    private function discordProvider(): DiscordProvider
    {
        $driver = Socialite::driver('discord');

        if (! $driver instanceof DiscordProvider) {
            throw new LogicException('The discord Socialite driver is not registered (see AppServiceProvider).');
        }

        return $driver;
    }
}
