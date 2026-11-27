<?php

declare(strict_types=1);

namespace App\Planning;

use App\Access\AccessSnapshot;
use App\Enums\RsvpStatus;
use App\Enums\SplitMode;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\EventExpense;
use App\Models\EventExpenseShare;
use App\Models\EventPayment;

/**
 * The Costs tab: expenses, payments, everyone's balance and the transfers
 * that settle it. All amounts are minor units of the event's currency.
 *
 * @phpstan-type Member array{id: int, name: string, avatar_url: string}
 */
final readonly class CostsPlan
{
    private const string MEMBER = '{ id: number; name: string; avatar_url: string }';

    /** Typefinder shape of {@see self::for()}. */
    public const string TYPE = '{ currency: string; total: number; people: '.self::MEMBER.'[]; expenses: { id: number; description: string; amount: number; split_mode: '.SplitMode::class.'; paid_by: '.self::MEMBER.'; shares: { member: '.self::MEMBER.'; amount: number }[]; created_at: string | null; can_remove: boolean }[]; payments: { id: number; from: '.self::MEMBER.'; to: '.self::MEMBER.'; amount: number; settled_at: string; can_remove: boolean }[]; balances: { member: '.self::MEMBER.'; paid: number; share: number; balance: number }[]; settle_up: { from: '.self::MEMBER.'; to: '.self::MEMBER.'; amount: number }[] }';

    /**
     * @return array{
     *     currency: string,
     *     total: int,
     *     people: list<Member>,
     *     expenses: list<array{id: int, description: string, amount: int, split_mode: SplitMode, paid_by: Member, shares: list<array{member: Member, amount: int}>, created_at: string|null, can_remove: bool}>,
     *     payments: list<array{id: int, from: Member, to: Member, amount: int, settled_at: string, can_remove: bool}>,
     *     balances: list<array{member: Member, paid: int, share: int, balance: int}>,
     *     settle_up: list<array{from: Member, to: Member, amount: int}>,
     * }
     */
    public static function for(Event $event, AccessSnapshot $access): array
    {
        $event->load(['expenses.paidBy', 'expenses.shares.discordUser', 'payments.from', 'payments.to', 'hosts', 'rsvps']);
        $organiser = EventRules::canEdit($access, $event);
        $people = DiscordUser::query()->whereIn('id', $event->costPeopleIds())->get()
            ->sortBy(fn (DiscordUser $user): string => mb_strtolower($user->display_name))
            ->keyBy('id');

        $ledger = self::ledger($event);

        $member = fn (int $id): array => ($people->get($id) ?? DiscordUser::query()->findOrFail($id))->toMember();
        $ownsOrOrganises = fn (int $creatorId): bool => $organiser || $creatorId === $access->discordUserId;

        return [
            'currency' => $event->currency->value,
            'total' => array_sum($event->expenses->map(fn (EventExpense $expense): int => $expense->amount)->all()),
            'people' => array_values($people->map(fn (DiscordUser $user): array => $user->toMember())->all()),
            'expenses' => array_values($event->expenses->sortByDesc('id')->map(fn (EventExpense $expense): array => [
                'id' => $expense->id,
                'description' => $expense->description,
                'amount' => $expense->amount,
                'split_mode' => $expense->split_mode,
                'paid_by' => $expense->paidBy->toMember(),
                'shares' => array_map(
                    fn (int $personId, int $amount): array => ['member' => $member($personId), 'amount' => $amount],
                    array_keys(self::shares($event, $expense)),
                    self::shares($event, $expense),
                ),
                'created_at' => $expense->created_at?->toIso8601String(),
                'can_remove' => $ownsOrOrganises($expense->added_by_discord_user_id) || $expense->paid_by_discord_user_id === $access->discordUserId,
            ])->all()),
            'payments' => array_values($event->payments->sortByDesc('id')->map(fn (EventPayment $payment): array => [
                'id' => $payment->id,
                'from' => $payment->from->toMember(),
                'to' => $payment->to->toMember(),
                'amount' => $payment->amount,
                'settled_at' => $payment->settled_at->toIso8601String(),
                'can_remove' => $ownsOrOrganises($payment->recorded_by_discord_user_id),
            ])->all()),
            'balances' => array_values(collect($ledger)
                ->sortBy(fn (array $row): int => -$row['balance'])
                ->map(fn (array $row, int $id): array => ['member' => $member($id), ...$row])
                ->all()),
            'settle_up' => array_map(fn (array $transfer): array => [
                'from' => $member($transfer['from']),
                'to' => $member($transfer['to']),
                'amount' => $transfer['amount'],
            ], SettleUp::transfers(array_map(fn (array $row): int => $row['balance'], $ledger))),
        ];
    }

    /**
     * What each person paid, their share of the expenses, and their balance
     * after payments (positive: is owed money).
     *
     * @return array<int, array{paid: int, share: int, balance: int}>
     */
    private static function ledger(Event $event): array
    {
        $ledger = [];
        $empty = ['paid' => 0, 'share' => 0, 'balance' => 0];

        foreach ($event->expenses as $expense) {
            $payer = $ledger[$expense->paid_by_discord_user_id] ?? $empty;
            $payer['paid'] += $expense->amount;
            $payer['balance'] += $expense->amount;
            $ledger[$expense->paid_by_discord_user_id] = $payer;

            foreach (self::shares($event, $expense) as $personId => $amount) {
                $sharer = $ledger[$personId] ?? $empty;
                $sharer['share'] += $amount;
                $sharer['balance'] -= $amount;
                $ledger[$personId] = $sharer;
            }
        }

        // Paying someone back settles debt: the payer owes less, the receiver is owed less.
        foreach ($event->payments as $payment) {
            $from = $ledger[$payment->from_discord_user_id] ?? $empty;
            $from['balance'] += $payment->amount;
            $ledger[$payment->from_discord_user_id] = $from;

            $to = $ledger[$payment->to_discord_user_id] ?? $empty;
            $to['balance'] -= $payment->amount;
            $ledger[$payment->to_discord_user_id] = $to;
        }

        return $ledger;
    }

    /**
     * Who carries how much of an expense. "Everyone going" is worked out
     * now, from the RSVPs (1 share each plus 1 per guest); with nobody
     * going, the payer carries it.
     *
     * @return array<int, int> discord_users.id => minor units
     */
    private static function shares(Event $event, EventExpense $expense): array
    {
        if ($expense->split_mode !== SplitMode::Attendees) {
            return $expense->shares->mapWithKeys(fn (EventExpenseShare $share): array => [$share->discord_user_id => $share->amount])->all();
        }

        $weights = [];

        foreach ($event->rsvps as $rsvp) {
            if ($rsvp->status === RsvpStatus::Going) {
                $weights[$rsvp->discord_user_id] = 1 + $rsvp->guests;
            }
        }

        return $weights === [] ? [$expense->paid_by_discord_user_id => $expense->amount] : Money::splitByWeight($expense->amount, $weights);
    }
}
