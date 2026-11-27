<?php

declare(strict_types=1);

namespace App\Providers;

use App\Access\GuildAccess;
use App\Ai\AiQuoteNormalizer;
use App\Ai\QuoteNormalizer;
use App\Discord\DiscordClient;
use App\Discord\HttpDiscordClient;
use App\Discord\Interactions\HttpInteractionAcknowledger;
use App\Discord\Interactions\InteractionAcknowledger;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Discord\DiscordExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DiscordClient::class, HttpDiscordClient::class);
        $this->app->bind(QuoteNormalizer::class, AiQuoteNormalizer::class);
        $this->app->bindIf(InteractionAcknowledger::class, HttpInteractionAcknowledger::class);

        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureProxies();
        $this->configureAuth();
    }

    /**
     * Trust the reverse proxy configured in app.trusted_proxies.
     */
    protected function configureProxies(): void
    {
        $proxies = config()->string('app.trusted_proxies');

        if ($proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_map(trim(...), explode(',', $proxies)));
        }
    }

    /**
     * Discord OAuth (Socialite) and the gates: `admin` (owners and guild
     * admin roles) and `manage-guilds` (owners only).
     */
    protected function configureAuth(): void
    {
        Event::listen(SocialiteWasCalled::class, [DiscordExtendSocialite::class, 'handle']);

        Gate::define('admin', fn (User $user): bool => app(GuildAccess::class)->isAdmin($user->discordUser));
        Gate::define('manage-guilds', fn (User $user): bool => app(GuildAccess::class)->isOwner($user->discordUser));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Resources as Inertia props are plain objects, not { data: … }.
        JsonResource::withoutWrapping();

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );
    }
}
