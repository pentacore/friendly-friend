<?php

declare(strict_types=1);

namespace App\Http\Requests\Events;

use App\Access\AccessSnapshot;
use App\Enums\RsvpStatus;
use App\Models\Event;
use App\Planning\EventRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Your answer to an event.
 */
final class RsvpRequest extends FormRequest
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
            'status' => ['required', Rule::enum(RsvpStatus::class)],
            'guests' => ['integer', 'min:0', 'max:20'],
            'note' => ['nullable', 'string', 'max:200'],
            'needs_ride' => ['boolean'],
            'answers' => ['array', 'max:50'],
            'answers.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * After the "answer by" time only Can't is accepted (organisers excepted).
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $event = $this->route('event');
            $access = AccessSnapshot::forRequest($this);
            $status = $this->enum('status', RsvpStatus::class);

            if (! $event instanceof Event || ! $access instanceof AccessSnapshot || ! $status instanceof RsvpStatus || $validator->errors()->isNotEmpty()) {
                return;
            }

            if (! EventRules::canAnswer($access, $event, $status)) {
                $validator->errors()->add('status', __('Answers closed on :date. You can still say you can’t make it.', [
                    'date' => $event->rsvp_by?->setTimezone($event->timezone)->format('j M H:i'),
                ]));
            }
        }];
    }
}
