<?php

declare(strict_types=1);

namespace App\Planning;

use App\Access\AccessSnapshot;
use App\Enums\MealSlot;
use App\Enums\RsvpStatus;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\EventBringClaim;
use App\Models\EventBringItem;
use App\Models\EventMeal;
use App\Models\EventRsvp;
use App\Models\EventShoppingItem;

/**
 * The Food tab of an event: bring list with claims, shopping list, meal
 * plan, and (for organisers only) the dietary needs of who's coming.
 *
 * @phpstan-type Member array{id: int, name: string, avatar_url: string}
 */
final readonly class FoodPlan
{
    /** Typefinder shape of {@see self::for()}. */
    public const string TYPE = '{ bring: { id: number; label: string; quantity_needed: number; claimed: number; claims: { member: { id: number; name: string; avatar_url: string }; quantity: number; note: string | null }[] }[]; shopping: { id: number; label: string; quantity: string | null; added_by: { id: number; name: string; avatar_url: string }; checked_by: { id: number; name: string; avatar_url: string } | null; paid_in: string | null; can_remove: boolean }[]; meals: { id: number; date: string; slot: '.MealSlot::class.'; title: string; notes: string | null; cooks: { id: number; name: string; avatar_url: string }[] }[]; dietary: { member: { id: number; name: string; avatar_url: string }; notes: string }[] | null }';

    /**
     * @return array{
     *     bring: list<array{id: int, label: string, quantity_needed: int, claimed: int, claims: list<array{member: Member, quantity: int, note: string|null}>}>,
     *     shopping: list<array{id: int, label: string, quantity: string|null, added_by: Member, checked_by: Member|null, paid_in: string|null, can_remove: bool}>,
     *     meals: list<array{id: int, date: string, slot: MealSlot, title: string, notes: string|null, cooks: list<Member>}>,
     *     dietary: list<array{member: Member, notes: string}>|null,
     * }
     */
    public static function for(Event $event, AccessSnapshot $access): array
    {
        $event->load(['bringItems.claims.discordUser', 'shoppingItems.addedBy', 'shoppingItems.checkedBy', 'shoppingItems.expense', 'meals.cooks', 'rsvps.discordUser', 'hosts']);
        $organiser = EventRules::canEdit($access, $event);

        return [
            'bring' => array_values($event->bringItems->sortBy('id')->map(fn (EventBringItem $item): array => [
                'id' => $item->id,
                'label' => $item->label,
                'quantity_needed' => $item->quantity_needed,
                'claimed' => $item->claims->sum(fn (EventBringClaim $claim): int => $claim->quantity),
                'claims' => array_values($item->claims->map(fn (EventBringClaim $claim): array => [
                    'member' => $claim->discordUser->toMember(),
                    'quantity' => $claim->quantity,
                    'note' => $claim->note,
                ])->all()),
            ])->all()),
            'shopping' => array_values($event->shoppingItems
                ->sortBy(fn (EventShoppingItem $item): string => ($item->checked_at !== null ? '1' : '0').str_pad((string) $item->id, 10, '0', STR_PAD_LEFT))
                ->map(fn (EventShoppingItem $item): array => [
                    'id' => $item->id,
                    'label' => $item->label,
                    'quantity' => $item->quantity,
                    'added_by' => $item->addedBy->toMember(),
                    'checked_by' => $item->checkedBy?->toMember(),
                    'paid_in' => $item->expense?->description,
                    'can_remove' => $organiser || $item->added_by_discord_user_id === $access->discordUserId,
                ])->all()),
            'meals' => array_values($event->meals
                ->sortBy(fn (EventMeal $meal): string => $meal->date->toDateString().array_search($meal->slot, MealSlot::cases(), true))
                ->map(fn (EventMeal $meal): array => [
                    'id' => $meal->id,
                    'date' => $meal->date->toDateString(),
                    'slot' => $meal->slot,
                    'title' => $meal->title,
                    'notes' => $meal->notes,
                    'cooks' => array_values($meal->cooks->map(fn (DiscordUser $cook): array => $cook->toMember())->all()),
                ])->all()),
            'dietary' => $organiser ? array_values($event->rsvps
                ->filter(fn (EventRsvp $rsvp): bool => $rsvp->status !== RsvpStatus::Declined && filled($rsvp->discordUser->dietary_notes))
                ->map(fn (EventRsvp $rsvp): array => ['member' => $rsvp->discordUser->toMember(), 'notes' => (string) $rsvp->discordUser->dietary_notes])
                ->all()) : null,
        ];
    }
}
