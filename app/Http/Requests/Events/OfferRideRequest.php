<?php

declare(strict_types=1);

namespace App\Http\Requests\Events;

use App\Enums\RideDirection;
use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Offering a ride: which way, how many free seats, from where and when
 * (local to the event's timezone).
 */
final class OfferRideRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event && ($this->user()?->can('participate', $event) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::enum(RideDirection::class)],
            'seats' => ['required', 'integer', 'min:1', 'max:8'],
            'departs_from' => ['nullable', 'string', 'max:120'],
            'departs_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function departsAt(Event $event): ?CarbonImmutable
    {
        return $this->filled('departs_at')
            ? (CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->string('departs_at')->value(), $event->timezone)?->utc())
            : null;
    }
}
