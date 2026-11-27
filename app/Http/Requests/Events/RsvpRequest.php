<?php

declare(strict_types=1);

namespace App\Http\Requests\Events;

use App\Enums\RsvpStatus;
use App\Models\Event;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        ];
    }
}
