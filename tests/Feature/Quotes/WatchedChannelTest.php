<?php

declare(strict_types=1);

use App\Ai\PlainQuoteNormalizer;
use App\Ai\QuoteNormalizer;
use App\Discord\DiscordClient;
use App\Discord\Gateway\GatewayEventRouter;
use App\Discord\Testing\FakeDiscordClient;
use App\Enums\QuoteSource;
use App\Enums\QuoteStatus;
use App\Models\DiscordUser;
use App\Models\Guild;
use App\Models\Quote;
use App\Models\QuoteImport;
use App\Models\User;
use App\Quotes\Actions\WatchChannels;
use App\Quotes\Jobs\CaptureFromMention;
use App\Quotes\Jobs\PickUpPostedQuote;
use App\Quotes\WatchedChannels;
use Illuminate\Support\Facades\Queue;
use Tests\Support\DiscordPayloads as P;

beforeEach(function (): void {
    config()->set('quotes.ai.providers', []);
    config()->set('discord.bot_token', 'token');
    config()->set('services.discord.client_id', '1000');
    app()->bind(QuoteNormalizer::class, PlainQuoteNormalizer::class);
    app()->instance(DiscordClient::class, $this->discord = new FakeDiscordClient);
    $this->guild = Guild::factory()->create(['discord_id' => '555', 'channels' => [['id' => '777', 'name' => 'quotes', 'position' => 1], ['id' => '778', 'name' => 'general', 'position' => 2]]]);
    $this->owner = DiscordUser::factory()->create();
});

function postedIn(string $channelId, string $content, string $id = '9001'): array
{
    return P::message($id, $content, '50', ['guild_id' => '555', 'channel_id' => $channelId]);
}

it('keeps a standing import per watched channel and finishes it when unwatched', function (): void {
    app(WatchChannels::class)->handle($this->guild, ['777'], $this->owner);
    app(WatchChannels::class)->handle($this->guild, ['777'], $this->owner);

    expect(QuoteImport::query()->where('status', 'watching')->pluck('channel_id')->all())->toBe(['777'])
        ->and(app(WatchedChannels::class)->contains('777'))->toBeTrue();

    app(WatchChannels::class)->handle($this->guild, [], $this->owner);

    expect(QuoteImport::query()->sole()->status)->toBe('done')
        ->and(app(WatchedChannels::class)->contains('777'))->toBeFalse();
});

it('lets owners pick watched channels on the Servers page', function (): void {
    config()->set('discord.owner_ids', [$this->owner->discord_id]);
    $owner = User::factory()->forDiscordUser($this->owner)->create();

    $this->actingAs($owner)->put(route('admin.guilds.update', $this->guild), [
        'enabled' => true, 'allow_all_members' => true, 'allowed_role_ids' => [], 'admin_role_ids' => [],
        'watched_channel_ids' => ['777', '999'],
    ])->assertSessionHasErrors('watched_channel_ids.1');

    $this->actingAs($owner)->put(route('admin.guilds.update', $this->guild), [
        'enabled' => true, 'allow_all_members' => true, 'allowed_role_ids' => [], 'admin_role_ids' => [],
        'watched_channel_ids' => ['777'],
    ])->assertSessionHasNoErrors();

    expect($this->guild->refresh()->watched_channel_ids)->toBe(['777']);
});

it('sends messages in watched channels to the pick-up job, but not mentions or other channels', function (): void {
    Queue::fake();
    app(WatchChannels::class)->handle($this->guild, ['777'], $this->owner);
    $router = app(GatewayEventRouter::class);

    $router->route('MESSAGE_CREATE', postedIn('777', '"cheese is a vegetable" - Bo'));
    $router->route('MESSAGE_CREATE', postedIn('778', '"cheese is a vegetable" - Bo'));
    $router->route('MESSAGE_CREATE', [...postedIn('777', 'save this', '9002'), 'mentions' => [['id' => '1000', 'username' => 'bot']]]);

    Queue::assertPushed(PickUpPostedQuote::class, 1);
    Queue::assertPushed(CaptureFromMention::class, 1);
});

it('queues a posted quote for review and reacts so people see it was noticed', function (): void {
    app(WatchChannels::class)->handle($this->guild, ['777'], $this->owner);

    dispatch_sync(new PickUpPostedQuote(postedIn('777', '"cheese is a vegetable" - Bo')));
    dispatch_sync(new PickUpPostedQuote(postedIn('777', 'lol anyone up for pizza', '9002')));

    $quote = Quote::query()->with('lines')->sole();
    $import = QuoteImport::query()->sole();
    expect($quote->status)->toBe(QuoteStatus::PendingReview)
        ->and($quote->source)->toBe(QuoteSource::WatchedChannel)
        ->and($quote->import_id)->toBe($import->id)
        ->and($quote->lines->first()?->text)->toBe('cheese is a vegetable')
        ->and([$import->messages_scanned, $import->quotes_found])->toBe([2, 1])
        ->and($this->discord->reactions())->toBe([['channel_id' => '777', 'message_id' => '9001', 'emoji' => '📝']]);
});

it('ignores channels that are no longer watched and disabled servers', function (): void {
    app(WatchChannels::class)->handle($this->guild, ['777'], $this->owner);
    $this->guild->update(['enabled' => false]);

    dispatch_sync(new PickUpPostedQuote(postedIn('777', '"cheese is a vegetable" - Bo')));
    dispatch_sync(new PickUpPostedQuote(postedIn('778', '"cheese is a vegetable" - Bo', '9003')));

    expect(Quote::query()->count())->toBe(0);
});

it('publishes picked-up quotes straight away when the server opted in', function (): void {
    app(WatchChannels::class)->handle($this->guild, ['777'], $this->owner, publishStraightAway: true);

    dispatch_sync(new PickUpPostedQuote(postedIn('777', '"cheese is a vegetable" - Bo')));

    $quote = Quote::query()->with('lines.person', 'revisions')->sole();
    expect($this->guild->refresh()->publish_watched_quotes)->toBeTrue()
        ->and($quote->status)->toBe(QuoteStatus::Published)
        ->and($quote->published_at)->not->toBeNull()
        ->and($quote->lines->first()?->person?->display_name)->toBe('Bo')
        ->and($quote->revisions)->toHaveCount(1)
        ->and($this->discord->reactions())->toHaveCount(1);
});

it('lets owners turn on publishing straight away on the Servers page', function (): void {
    config()->set('discord.owner_ids', [$this->owner->discord_id]);
    $owner = User::factory()->forDiscordUser($this->owner)->create();

    $this->actingAs($owner)->put(route('admin.guilds.update', $this->guild), [
        'enabled' => true, 'allow_all_members' => true, 'allowed_role_ids' => [], 'admin_role_ids' => [],
        'watched_channel_ids' => ['777'], 'publish_watched_quotes' => true,
    ])->assertSessionHasNoErrors();

    expect($this->guild->refresh()->publish_watched_quotes)->toBeTrue();
});
