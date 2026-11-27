<?php

declare(strict_types=1);

namespace App\Http\Requests\Events;

use App\Enums\SplitMode;
use App\Models\Event;
use App\Planning\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * An expense: what, how much (as typed, "120,50"), who paid, and who
 * shares it (equally, or exact amounts that add up), and which shopping
 * list items it paid for.
 */
final class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event && ($this->user()?->can('manageCosts', $event) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $event = $this->route('event');
        $people = $event instanceof Event ? $event->costPeopleIds() : [];
        $shoppingItemIds = $event instanceof Event ? $event->shoppingItems()->pluck('id')->all() : [];

        return [
            'description' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'string', 'regex:'.Money::PATTERN],
            'paid_by' => ['required', 'integer', Rule::in($people)],
            'split_mode' => ['required', Rule::enum(SplitMode::class)],
            'people' => ['exclude_if:split_mode,'.SplitMode::Attendees->value, 'required', 'array', 'min:1'],
            'people.*' => ['exclude_if:split_mode,'.SplitMode::Attendees->value, 'integer', 'distinct', Rule::in($people)],
            'exact' => ['array'],
            'exact.*' => ['nullable', 'string', 'regex:'.Money::PATTERN],
            'shopping_item_ids' => ['array'],
            'shopping_item_ids.*' => ['integer', 'distinct', Rule::in($shoppingItemIds)],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->mode() !== SplitMode::Exact) {
                return;
            }

            $total = Money::toMinor($this->string('amount')->value());
            $sum = array_sum($this->shares());

            if ($sum !== $total) {
                $validator->errors()->add('exact', __('The parts add up to :sum, not :total.', [
                    'sum' => number_format($sum / 100, 2),
                    'total' => number_format($total / 100, 2),
                ]));
            }
        }];
    }

    public function amount(): int
    {
        return Money::toMinor($this->string('amount')->value());
    }

    /**
     * @return list<int>
     */
    public function shoppingItemIds(): array
    {
        return array_values(array_map(intval(...), array_filter($this->array('shopping_item_ids'), is_numeric(...))));
    }

    public function mode(): SplitMode
    {
        return $this->enum('split_mode', SplitMode::class) ?? SplitMode::Equal;
    }

    /**
     * discord_users.id => minor units.
     *
     * @return array<int, int>
     */
    public function shares(): array
    {
        $people = array_values(array_map(intval(...), array_filter($this->array('people'), is_numeric(...))));

        if ($this->mode() === SplitMode::Attendees) {
            return [];
        }

        if ($this->mode() === SplitMode::Equal) {
            return Money::splitEqually($this->amount(), $people);
        }

        $exact = $this->array('exact');
        $shares = [];

        foreach ($people as $person) {
            $typed = $exact[$person] ?? null;
            $shares[$person] = is_string($typed) && $typed !== '' ? Money::toMinor($typed) : 0;
        }

        return array_filter($shares, fn (int $share): bool => $share > 0);
    }
}
