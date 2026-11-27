<?php

declare(strict_types=1);

namespace App\Http\Requests\Events;

use App\Models\Event;
use App\Planning\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A payment between two people of the event, as typed ("120,50").
 */
final class PaymentRequest extends FormRequest
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

        return [
            'from' => ['required', 'integer', Rule::in($people)],
            'to' => ['required', 'integer', 'different:from', Rule::in($people)],
            'amount' => ['required', 'string', 'regex:'.Money::PATTERN, 'not_regex:/^0+([.,]0+)?$/'],
        ];
    }
}
