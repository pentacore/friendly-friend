<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Access\AccessSnapshot;
use App\Enums\RsvpStatus;
use App\Http\Requests\Events\SaveEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Guild;
use App\Models\GuildMember;
use App\Models\User;
use App\Planning\Actions\CancelEvent;
use App\Planning\Actions\CreateEvent;
use App\Planning\Actions\UpdateEvent;
use App\Planning\CostsPlan;
use App\Planning\DatePollPlan;
use App\Planning\FoodPlan;
use App\Planning\QuestionsPlan;
use App\Planning\TransportPlan;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use Pentacore\Typefinder\Attributes\TypefinderPage;

/**
 * Event lists, the event page and the event form.
 */
final class EventController extends Controller
{
    public const string MEMBER = '{ id: number; name: string; avatar_url: string }';

    /** Without an end time, an event counts as upcoming until this long after it starts. */
    private const int ASSUMED_HOURS = 6;

    #[TypefinderPage(component: 'events/Index', props: [
        'upcoming' => EventResource::class.'[]',
        'past' => EventResource::class.'[]',
        'filters' => '{ guild: number | null }',
        'guilds' => '{ id: number; name: string }[]',
    ])]
    public function index(Request $request): Response
    {
        $access = $this->access($request);
        $guildId = $access->canSeeGuild($request->integer('guild')) ? $request->integer('guild') : null;
        $events = fn (): Builder => Event::query()
            ->whereIn('guild_id', $guildId !== null ? [$guildId] : $access->guildIds)
            ->with(['guild', 'createdBy', 'hosts', 'rsvps']);
        $stillOn = 'coalesce(ends_at, starts_at + interval \''.self::ASSUMED_HOURS.' hours\')';

        return Inertia::render('events/Index', [
            'upcoming' => EventResource::collection($events()->whereRaw("{$stillOn} >= ?", [now()])->orderBy('starts_at')->get()),
            'past' => EventResource::collection($events()->whereRaw("{$stillOn} < ?", [now()])->orderByDesc('starts_at')->limit(30)->get()),
            'filters' => ['guild' => $guildId],
            'guilds' => $this->guilds($access),
        ]);
    }

    #[TypefinderPage(component: 'events/Create', props: [
        'guilds' => '{ id: number; name: string }[]',
        'members' => 'Record<number, '.self::MEMBER.'[]>',
        'default_timezone' => 'string',
    ])]
    public function create(Request $request): Response
    {
        $access = $this->access($request);

        return Inertia::render('events/Create', [
            'guilds' => $this->guilds($access),
            'members' => $this->membersByGuild($access->guildIds),
            'default_timezone' => config()->string('stats.timezone'),
        ]);
    }

    public function store(SaveEventRequest $request, CreateEvent $create): RedirectResponse
    {
        $guild = Guild::query()->findOrFail($request->integer('guild_id'));
        $event = $create->handle($guild, $this->user($request)->discordUser, $request->details());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event created.')]);

        return to_route('events.show', $event);
    }

    #[TypefinderPage(component: 'events/Show', props: [
        'event' => EventResource::class,
        'attendees' => '{ member: '.self::MEMBER.'; status: '.RsvpStatus::class.'; guests: number; note: string | null; needs_ride: boolean }[]',
        'questions' => QuestionsPlan::TYPE,
        'date_poll' => DatePollPlan::TYPE,
        'food' => FoodPlan::TYPE,
        'transport' => TransportPlan::TYPE,
        'costs' => CostsPlan::TYPE,
        'me' => 'number',
    ])]
    public function show(Request $request, Event $event): Response
    {
        Gate::authorize('view', $event);
        $access = $this->access($request);
        $event->load(['guild', 'createdBy', 'hosts', 'rsvps.discordUser']);

        return Inertia::render('events/Show', [
            'event' => new EventResource($event),
            'attendees' => array_values($event->rsvps
                ->sortBy(fn (EventRsvp $rsvp): string => array_search($rsvp->status, RsvpStatus::cases(), true).$rsvp->discordUser->display_name)
                ->map(fn (EventRsvp $rsvp): array => [
                    'member' => $rsvp->discordUser->toMember(),
                    'status' => $rsvp->status,
                    'guests' => $rsvp->guests,
                    'note' => $rsvp->note,
                    'needs_ride' => $rsvp->needs_ride,
                ])->all()),
            'questions' => QuestionsPlan::for($event, $access),
            'date_poll' => DatePollPlan::for($event, $access),
            'food' => fn (): array => FoodPlan::for($event, $access),
            'transport' => fn (): array => TransportPlan::for($event, $access),
            'costs' => fn (): array => CostsPlan::for($event, $access),
            'me' => $access->discordUserId,
        ]);
    }

    #[TypefinderPage(component: 'events/Edit', props: [
        'event' => EventResource::class,
        'form' => '{ title: string; description: string; location: string; location_url: string; starts_at: string; ends_at: string; rsvp_by: string; timezone: string; host_ids: number[] }',
        'members' => self::MEMBER.'[]',
    ])]
    public function edit(Event $event): Response
    {
        Gate::authorize('update', $event);
        $event->load(['guild', 'createdBy', 'hosts', 'rsvps']);
        $local = fn (?DateTimeInterface $at): string => $at instanceof DateTimeInterface ? CarbonImmutable::instance($at)->setTimezone($event->timezone)->format('Y-m-d\TH:i') : '';

        return Inertia::render('events/Edit', [
            'event' => new EventResource($event),
            'form' => [
                'title' => $event->title,
                'description' => $event->description ?? '',
                'location' => $event->location ?? '',
                'location_url' => $event->location_url ?? '',
                'starts_at' => $local($event->starts_at),
                'ends_at' => $local($event->ends_at),
                'rsvp_by' => $local($event->rsvp_by),
                'timezone' => $event->timezone,
                'host_ids' => array_values($event->hosts->modelKeys()),
            ],
            'members' => $this->membersByGuild([$event->guild_id])[$event->guild_id] ?? [],
        ]);
    }

    public function update(SaveEventRequest $request, Event $event, UpdateEvent $update): RedirectResponse
    {
        $update->handle($event, $request->details());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event updated.')]);

        return to_route('events.show', $event);
    }

    public function cancel(Request $request, Event $event, CancelEvent $cancel): RedirectResponse
    {
        Gate::authorize('update', $event);
        $cancelled = $request->boolean('cancelled', true);
        $cancel->handle($event, $cancelled);

        Inertia::flash('toast', ['type' => 'success', 'message' => $cancelled ? __('Event cancelled.') : __('Event is back on.')]);

        return back();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function guilds(AccessSnapshot $access): array
    {
        return array_values(Guild::query()->whereIn('id', $access->guildIds)->orderBy('name')->get(['id', 'name'])
            ->map(fn (Guild $guild): array => ['id' => $guild->id, 'name' => $guild->name])->all());
    }

    /**
     * Current members of each server, for the co-host picker.
     *
     * @param  list<int>  $guildIds
     * @return array<int, list<array{id: int, name: string, avatar_url: string}>>
     */
    private function membersByGuild(array $guildIds): array
    {
        $byGuild = array_fill_keys($guildIds, []);

        GuildMember::query()->with('discordUser')->whereIn('guild_id', $guildIds)->where('in_guild', true)->get()
            ->sortBy(fn (GuildMember $member): string => mb_strtolower($member->discordUser->display_name))
            ->each(function (GuildMember $member) use (&$byGuild): void {
                $byGuild[$member->guild_id][] = $member->discordUser->toMember();
            });

        return $byGuild;
    }

    private function access(Request $request): AccessSnapshot
    {
        return AccessSnapshot::forRequest($request) ?? throw new LogicException('Events require a logged-in user.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new LogicException('Events require a logged-in user.');
    }
}
