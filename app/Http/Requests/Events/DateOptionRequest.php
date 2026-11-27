<?php

declare(strict_types=1);

namespace App\Http\Requests\Events;

use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Another proposed date (organisers), local to the event's timezone.
 */
final class DateOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event && $event->date_polling && ($this->user()?->can('update', $event) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
        ];
    }

    public function local(Event $event, string $key): ?CarbonImmutable
    {
        return $this->filled($key)
            ? CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->string($key)->value(), $event->timezone)?->utc()
            : null;
    }
}
