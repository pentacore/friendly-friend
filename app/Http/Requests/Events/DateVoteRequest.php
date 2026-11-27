<?php

declare(strict_types=1);

namespace App\Http\Requests\Events;

use App\Enums\DateVote;
use App\Models\Event;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Your yes / if need be / no for a proposed date (empty takes it back).
 */
final class DateVoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event && $event->date_polling && ($this->user()?->can('participate', $event) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'answer' => ['nullable', Rule::enum(DateVote::class)],
        ];
    }
}
