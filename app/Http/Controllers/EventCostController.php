<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Events\ExpenseRequest;
use App\Http\Requests\Events\PaymentRequest;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\EventExpense;
use App\Models\EventPayment;
use App\Models\User;
use App\Planning\Actions\Costs\AddExpense;
use App\Planning\Actions\Costs\RecordPayment;
use App\Planning\Actions\Costs\RemoveExpense;
use App\Planning\Actions\Costs\RemovePayment;
use App\Planning\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use LogicException;

/**
 * Writes on an event's Costs tab: expenses and payments.
 */
final class EventCostController extends Controller
{
    public function storeExpense(ExpenseRequest $request, Event $event, AddExpense $add): RedirectResponse
    {
        $add->handle(
            $event,
            $this->member($request),
            $request->integer('paid_by'),
            $request->string('description')->trim()->value(),
            $request->amount(),
            $request->mode(),
            $request->shares(),
        );

        return back();
    }

    public function destroyExpense(Request $request, Event $event, EventExpense $expense, RemoveExpense $remove): RedirectResponse
    {
        $me = $this->member($request)->id;
        abort_unless($expense->added_by_discord_user_id === $me || $expense->paid_by_discord_user_id === $me || Gate::allows('update', $event), 403);
        $remove->handle($expense);

        return back();
    }

    public function storePayment(PaymentRequest $request, Event $event, RecordPayment $record): RedirectResponse
    {
        $record->handle($event, $request->integer('from'), $request->integer('to'), Money::toMinor($request->string('amount')->value()), $this->member($request));

        return back();
    }

    public function destroyPayment(Request $request, Event $event, EventPayment $payment, RemovePayment $remove): RedirectResponse
    {
        abort_unless($payment->recorded_by_discord_user_id === $this->member($request)->id || Gate::allows('update', $event), 403);
        $remove->handle($payment);

        return back();
    }

    private function member(Request $request): DiscordUser
    {
        $user = $request->user();

        return $user instanceof User ? $user->discordUser : throw new LogicException('Costs require a logged-in user.');
    }
}
