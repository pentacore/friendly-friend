<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MealSlot;
use App\Http\Requests\Events\BringItemRequest;
use App\Http\Requests\Events\ClaimRequest;
use App\Http\Requests\Events\CookRequest;
use App\Http\Requests\Events\MealRequest;
use App\Http\Requests\Events\ShoppingItemRequest;
use App\Http\Requests\Events\TickRequest;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\EventBringItem;
use App\Models\EventMeal;
use App\Models\EventShoppingItem;
use App\Models\User;
use App\Planning\Actions\Food\AddBringItem;
use App\Planning\Actions\Food\AddShoppingItem;
use App\Planning\Actions\Food\ClaimBringItem;
use App\Planning\Actions\Food\RemoveBringItem;
use App\Planning\Actions\Food\RemoveMeal;
use App\Planning\Actions\Food\RemoveShoppingItem;
use App\Planning\Actions\Food\SaveMeal;
use App\Planning\Actions\Food\TickShoppingItem;
use App\Planning\Actions\Food\ToggleCook;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use LogicException;

/**
 * Writes on an event's Food tab: bring list, shopping list, meal plan.
 */
final class EventFoodController extends Controller
{
    public function storeBringItem(BringItemRequest $request, Event $event, AddBringItem $add): RedirectResponse
    {
        $add->handle($event, $request->string('label')->trim()->value(), $request->integer('quantity_needed', 1));

        return back();
    }

    public function destroyBringItem(Event $event, EventBringItem $bringItem, RemoveBringItem $remove): RedirectResponse
    {
        Gate::authorize('update', $event);
        $remove->handle($bringItem);

        return back();
    }

    public function claim(ClaimRequest $request, Event $event, EventBringItem $bringItem, ClaimBringItem $claim): RedirectResponse
    {
        $claim->handle($bringItem, $this->member($request), $request->integer('quantity'), $request->filled('note') ? $request->string('note')->trim()->value() : null);

        return back();
    }

    public function storeShoppingItem(ShoppingItemRequest $request, Event $event, AddShoppingItem $add): RedirectResponse
    {
        $add->handle($event, $this->member($request), $request->string('label')->trim()->value(), $request->filled('quantity') ? $request->string('quantity')->trim()->value() : null);

        return back();
    }

    public function tick(TickRequest $request, Event $event, EventShoppingItem $shoppingItem, TickShoppingItem $tick): RedirectResponse
    {
        $tick->handle($shoppingItem, $this->member($request), $request->boolean('bought'));

        return back();
    }

    public function destroyShoppingItem(Request $request, Event $event, EventShoppingItem $shoppingItem, RemoveShoppingItem $remove): RedirectResponse
    {
        abort_unless($shoppingItem->added_by_discord_user_id === $this->member($request)->id || Gate::allows('update', $event), 403);
        $remove->handle($shoppingItem);

        return back();
    }

    public function storeMeal(MealRequest $request, Event $event, SaveMeal $save): RedirectResponse
    {
        return $this->saveMeal($request, $event, null, $save);
    }

    public function updateMeal(MealRequest $request, Event $event, EventMeal $meal, SaveMeal $save): RedirectResponse
    {
        return $this->saveMeal($request, $event, $meal, $save);
    }

    public function destroyMeal(Event $event, EventMeal $meal, RemoveMeal $remove): RedirectResponse
    {
        Gate::authorize('update', $event);
        $remove->handle($meal);

        return back();
    }

    public function cook(CookRequest $request, Event $event, EventMeal $meal, ToggleCook $toggle): RedirectResponse
    {
        $toggle->handle($meal, $this->member($request), $request->boolean('cooking'));

        return back();
    }

    private function saveMeal(MealRequest $request, Event $event, ?EventMeal $meal, SaveMeal $save): RedirectResponse
    {
        $save->handle(
            $event,
            $meal,
            CarbonImmutable::createFromFormat('Y-m-d', $request->string('date')->value()) ?: CarbonImmutable::today(),
            $request->enum('slot', MealSlot::class) ?? MealSlot::Dinner,
            $request->string('title')->trim()->value(),
            $request->filled('notes') ? $request->string('notes')->trim()->value() : null,
        );

        return back();
    }

    private function member(Request $request): DiscordUser
    {
        $user = $request->user();

        return $user instanceof User ? $user->discordUser : throw new LogicException('Food planning requires a logged-in user.');
    }
}
