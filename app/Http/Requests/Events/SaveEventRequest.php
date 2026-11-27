<?php

declare(strict_types=1);

namespace App\Http\Requests\Events;

use App\Access\AccessSnapshot;
use App\Models\Event;
use App\Planning\Data\EventDetails;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'starts_at' => ['required', 'date_format:'.self::LOCAL_TIME],
            'ends_at' => ['nullable', 'date_format:'.self::LOCAL_TIME, 'after:starts_at'],
            'rsvp_by' => ['nullable', 'date_format:'.self::LOCAL_TIME, 'before_or_equal:starts_at'],
            'timezone' => ['required', 'timezone:all'],
            'host_ids' => ['array', 'max:20'],
            'host_ids.*' => ['integer'],
        ];
    }

    public function details(): EventDetails
    {
        $timezone = $this->string('timezone')->value();
        $local = fn (string $key): ?CarbonImmutable => $this->filled($key)
            ? CarbonImmutable::createFromFormat(self::LOCAL_TIME, $this->string($key)->value(), $timezone)?->utc()
            : null;

        return new EventDetails(
            title: $this->string('title')->trim()->value(),
            description: $this->filled('description') ? $this->string('description')->trim()->value() : null,
            location: $this->filled('location') ? $this->string('location')->trim()->value() : null,
            locationUrl: $this->filled('location_url') ? $this->string('location_url')->value() : null,
            startsAt: $local('starts_at') ?? CarbonImmutable::now(),
            endsAt: $local('ends_at'),
            rsvpBy: $local('rsvp_by'),
            timezone: $timezone,
            hostIds: array_values(array_map(intval(...), array_filter($this->array('host_ids'), is_numeric(...)))),
        );
    }
}
