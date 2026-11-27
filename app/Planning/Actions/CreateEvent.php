<?php

declare(strict_types=1);

namespace App\Planning\Actions;

use App\Enums\Currency;
use App\Enums\RsvpStatus;
use App\Enums\SplitMode;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\Guild;
use App\Planning\Actions\Costs\AddExpense;
use App\Planning\Actions\Food\AddBringItem;
use App\Planning\Actions\Food\AddShoppingItem;
use App\Planning\Actions\Food\SaveMeal;
use App\Planning\Actions\Questions\AddQuestion;
use App\Planning\Actions\Transport\OfferRide;
use App\Planning\Data\EventDetails;
use App\Planning\Data\EventPlan;
use App\Planning\EventAttributes;
use App\Planning\Jobs\SyncEventToDiscord;
use Illuminate\Support\Facades\DB;

/**
 * Creates an event on a server, with whatever was planned right away
 * (questions, food, the creator's ride, costs). The creator is going, and
 * Discord gets the announcement and the native event.
 */
final readonly class CreateEvent
{
    public function __construct(
        private EventAttributes $attributes,
        private RecordRsvp $recordRsvp,
        private AddQuestion $addQuestion,
        private AddBringItem $addBringItem,
        private AddShoppingItem $addShoppingItem,
        private SaveMeal $saveMeal,
        private OfferRide $offerRide,
        private AddExpense $addExpense,
    ) {}

    public function handle(Guild $guild, DiscordUser $creator, EventDetails $details, EventPlan $plan = new EventPlan): Event
    {
        $event = DB::transaction(function () use ($guild, $creator, $details): Event {
            $event = Event::query()->create([
                'guild_id' => $guild->id,
                'created_by_discord_user_id' => $creator->id,
                'currency' => Currency::Sek,
                ...$this->attributes->columns($details),
                'date_polling' => count($details->dateOptions) >= 2,
            ]);

            if ($event->date_polling) {
                $event->dateOptions()->createMany($details->dateOptions);
                $event->update(['ends_at' => null]);
            }

            $this->attributes->syncHosts($event, $details->hostIds);

            return $event;
        });

        $this->recordRsvp->handle($event, $creator, RsvpStatus::Going);
        $this->planAhead($event, $creator, $plan);
        dispatch(new SyncEventToDiscord($event->id));

        return $event;
    }

    private function planAhead(Event $event, DiscordUser $creator, EventPlan $plan): void
    {
        foreach ($plan->questions as $question) {
            $this->addQuestion->handle($event, $question['prompt'], $question['kind'], $question['options']);
        }

        foreach ($plan->bring as $item) {
            $this->addBringItem->handle($event, $item['label'], $item['quantity']);
        }

        foreach ($plan->shopping as $item) {
            $this->addShoppingItem->handle($event, $creator, $item['label'], $item['quantity']);
        }

        foreach ($plan->meals as $meal) {
            $this->saveMeal->handle($event, null, $meal['date'], $meal['slot'], $meal['title'], null);
        }

        if ($plan->ride !== null) {
            $this->offerRide->handle($event, $creator, $plan->ride['direction'], $plan->ride['seats'], $plan->ride['departs_from'], $plan->ride['departs_at'], null);
        }

        // Paid by the creator; split among everyone going, worked out as people answer.
        foreach ($plan->costs as $cost) {
            $this->addExpense->handle($event, $creator, $creator->id, $cost['description'], $cost['amount'], SplitMode::Attendees, []);
        }
    }
}
