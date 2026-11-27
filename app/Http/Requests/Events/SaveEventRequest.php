<?php

declare(strict_types=1);

namespace App\Http\Requests\Events;

use App\Access\AccessSnapshot;
use App\Enums\Currency;
use App\Enums\MealSlot;
use App\Enums\QuestionKind;
use App\Enums\RideDirection;
use App\Models\Event;
use App\Planning\Data\EventDetails;
use App\Planning\Data\EventPlan;
use App\Planning\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The event form (create and edit). Times are local to `timezone`, as
 * `<input type="datetime-local">` sends them.
 */
final class SaveEventRequest extends FormRequest
{
    private const string LOCAL_TIME = 'Y-m-d\TH:i';

    public function authorize(): bool
    {
        $event = $this->route('event');

        return ! $event instanceof Event || ($this->user()?->can('update', $event) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $creating = ! $this->route('event') instanceof Event;

        return [
            'guild_id' => $creating ? ['required', 'integer', Rule::in(AccessSnapshot::forRequest($this)->guildIds ?? [])] : ['prohibited'],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:200'],
            'location_url' => ['nullable', 'url:http,https', 'max:500'],
            'starts_at' => ['required_without:date_options', 'nullable', 'date_format:'.self::LOCAL_TIME],
            'ends_at' => ['nullable', 'date_format:'.self::LOCAL_TIME, 'after:starts_at'],
            'rsvp_by' => ['nullable', 'date_format:'.self::LOCAL_TIME, 'before_or_equal:starts_at'],
            'timezone' => ['required', 'timezone:all'],
            'currency' => ['sometimes', Rule::enum(Currency::class)],
            // Creating with a date vote instead of a fixed date (2 to 10 proposed dates).
            'date_options' => ['nullable', 'array', 'min:2', 'max:10'],
            'date_options.*.starts_at' => ['required', 'date_format:'.self::LOCAL_TIME],
            'date_options.*.ends_at' => ['nullable', 'date_format:'.self::LOCAL_TIME],
            'host_ids' => ['array', 'max:20'],
            // Planned right away when creating (ignored when editing; each part has its own tab later).
            'questions' => ['nullable', 'array', 'max:20'],
            'questions.*.prompt' => ['required', 'string', 'max:200'],
            'questions.*.kind' => ['required', Rule::enum(QuestionKind::class)],
            'questions.*.options' => ['nullable', 'array', 'max:12'],
            'questions.*.options.*' => ['nullable', 'string', 'max:80'],
            'bring' => ['nullable', 'array', 'max:50'],
            'bring.*.label' => ['required', 'string', 'max:120'],
            'bring.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'shopping' => ['nullable', 'array', 'max:100'],
            'shopping.*.label' => ['required', 'string', 'max:120'],
            'shopping.*.quantity' => ['nullable', 'string', 'max:40'],
            'meals' => ['nullable', 'array', 'max:30'],
            'meals.*.date' => ['required', 'date_format:Y-m-d'],
            'meals.*.slot' => ['required', Rule::enum(MealSlot::class)],
            'meals.*.title' => ['required', 'string', 'max:120'],
            'ride' => ['nullable', 'array'],
            'ride.direction' => ['required_with:ride', Rule::enum(RideDirection::class)],
            'ride.seats' => ['required_with:ride', 'integer', 'min:1', 'max:8'],
            'ride.departs_from' => ['nullable', 'string', 'max:120'],
            'ride.departs_at' => ['nullable', 'date_format:'.self::LOCAL_TIME],
            'costs' => ['nullable', 'array', 'max:30'],
            'costs.*.description' => ['required', 'string', 'max:120'],
            'costs.*.amount' => ['required', 'string', 'regex:'.Money::PATTERN, 'not_regex:/^0+([.,]0+)?$/'],
            'host_ids.*' => ['integer'],
        ];
    }

    /**
     * Times must be in the future: the start, every proposed date and the
     * answer-by time. On an existing event only a changed time is checked,
     * so a past event can still be tidied up.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $event = $this->route('event');
            $timezone = $this->string('timezone')->value();
            $now = CarbonImmutable::now();

            $times = ['starts_at' => $this->input('starts_at'), 'rsvp_by' => $this->input('rsvp_by')];

            foreach ($this->array('date_options') as $index => $option) {
                $times["date_options.{$index}.starts_at"] = is_array($option) ? ($option['starts_at'] ?? null) : null;

                // Same format, so comparing the strings compares the times.
                if (is_array($option) && is_string($option['starts_at'] ?? null) && is_string($option['ends_at'] ?? null) && $option['ends_at'] <= $option['starts_at']) {
                    $validator->errors()->add("date_options.{$index}.ends_at", __('The end must be after the start.'));
                }
            }

            // Amounts are stored in the event's currency, so it's fixed once there are costs.
            if ($event instanceof Event && $this->filled('currency') && $this->input('currency') !== $event->currency->value && $event->hasCosts()) {
                $validator->errors()->add('currency', __('The currency can\'t change once there are costs.'));
            }

            foreach ($this->array('questions') as $index => $question) {
                if (is_array($question) && ($question['kind'] ?? null) === QuestionKind::Choice->value && count(array_filter(is_array($question['options'] ?? null) ? $question['options'] : [], fn (mixed $option): bool => is_string($option) && trim($option) !== '')) < 2) {
                    $validator->errors()->add("questions.{$index}.options", __('Give at least two options.'));
                }
            }

            foreach ($times as $key => $value) {
                if (! is_string($value) || $value === '') {
                    continue;
                }

                $unchanged = $event instanceof Event && in_array($key, ['starts_at', 'rsvp_by'], true)
                    && $event->{$key}?->copy()->setTimezone($event->timezone)->format(self::LOCAL_TIME) === $value;
                $at = CarbonImmutable::createFromFormat(self::LOCAL_TIME, $value, $timezone);

                if (! $unchanged && $at instanceof CarbonImmutable && $at->lessThanOrEqualTo($now)) {
                    $validator->errors()->add($key, __('Pick a time in the future.'));
                }
            }
        }];
    }

    /**
     * The optional plan-ahead parts of a new event.
     */
    public function plan(): EventPlan
    {
        $timezone = $this->string('timezone')->value();
        $rows = fn (string $key): array => array_values(array_filter($this->array($key), is_array(...)));
        $text = fn (array $row, string $key): string => is_string($row[$key] ?? null) ? trim($row[$key]) : '';
        $ride = $this->array('ride');
        $departsAt = is_string($ride['departs_at'] ?? null) && $ride['departs_at'] !== ''
            ? CarbonImmutable::createFromFormat(self::LOCAL_TIME, $ride['departs_at'], $timezone)?->utc()
            : null;

        return new EventPlan(
            questions: array_map(fn (array $row): array => [
                'prompt' => $text($row, 'prompt'),
                'kind' => QuestionKind::tryFrom($text($row, 'kind')) ?? QuestionKind::YesNo,
                'options' => array_values(array_filter(array_map(fn (mixed $option): string => is_string($option) ? trim($option) : '', is_array($row['options'] ?? null) ? $row['options'] : []))),
            ], $rows('questions')),
            bring: array_map(fn (array $row): array => ['label' => $text($row, 'label'), 'quantity' => is_numeric($row['quantity'] ?? null) ? (int) $row['quantity'] : 1], $rows('bring')),
            shopping: array_map(fn (array $row): array => ['label' => $text($row, 'label'), 'quantity' => $text($row, 'quantity') !== '' ? $text($row, 'quantity') : null], $rows('shopping')),
            meals: array_values(array_filter(array_map(function (array $row) use ($text): ?array {
                $date = CarbonImmutable::createFromFormat('Y-m-d', $text($row, 'date'));

                return $date instanceof CarbonImmutable ? ['date' => $date, 'slot' => MealSlot::tryFrom($text($row, 'slot')) ?? MealSlot::Dinner, 'title' => $text($row, 'title')] : null;
            }, $rows('meals')))),
            ride: $ride === [] ? null : [
                'direction' => RideDirection::tryFrom(is_string($ride['direction'] ?? null) ? $ride['direction'] : '') ?? RideDirection::Both,
                'seats' => is_numeric($ride['seats'] ?? null) ? (int) $ride['seats'] : 1,
                'departs_from' => is_string($ride['departs_from'] ?? null) && trim($ride['departs_from']) !== '' ? trim($ride['departs_from']) : null,
                'departs_at' => $departsAt,
            ],
            costs: array_map(fn (array $row): array => ['description' => $text($row, 'description'), 'amount' => Money::toMinor($text($row, 'amount'))], $rows('costs')),
        );
    }

    public function details(): EventDetails
    {
        $timezone = $this->string('timezone')->value();
        $local = fn (string $key): ?CarbonImmutable => $this->filled($key)
            ? CarbonImmutable::createFromFormat(self::LOCAL_TIME, $this->string($key)->value(), $timezone)?->utc()
            : null;

        $options = [];

        foreach ($this->array('date_options') as $option) {
            $start = is_array($option) && is_string($option['starts_at'] ?? null) ? CarbonImmutable::createFromFormat(self::LOCAL_TIME, $option['starts_at'], $timezone)?->utc() : null;
            $end = is_array($option) && is_string($option['ends_at'] ?? null) && $option['ends_at'] !== '' ? CarbonImmutable::createFromFormat(self::LOCAL_TIME, $option['ends_at'], $timezone)?->utc() : null;

            if ($start instanceof CarbonImmutable) {
                $options[] = ['starts_at' => $start, 'ends_at' => $end];
            }
        }

        usort($options, fn (array $a, array $b): int => $a['starts_at'] <=> $b['starts_at']);

        return new EventDetails(
            title: $this->string('title')->trim()->value(),
            description: $this->filled('description') ? $this->string('description')->trim()->value() : null,
            location: $this->filled('location') ? $this->string('location')->trim()->value() : null,
            locationUrl: $this->filled('location_url') ? $this->string('location_url')->value() : null,
            startsAt: $local('starts_at') ?? $options[0]['starts_at'] ?? CarbonImmutable::now(),
            endsAt: $local('ends_at'),
            rsvpBy: $local('rsvp_by'),
            timezone: $timezone,
            hostIds: array_values(array_map(intval(...), array_filter($this->array('host_ids'), is_numeric(...)))),
            dateOptions: $options,
            currency: $this->enum('currency', Currency::class),
        );
    }
}
