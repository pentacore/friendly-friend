<?php

declare(strict_types=1);

namespace App\Http\Requests\Events;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters of the events list (query string).
 */
final class EventIndexRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /** @query */
            'q' => ['nullable', 'string', 'max:100'],
            /** @query */
            'guild' => ['nullable', 'integer'],
        ];
    }

    /**
     * An `ilike` pattern for the search, with `%` and `_` taken literally,
     * or null without one.
     */
    public function searchPattern(): ?string
    {
        $search = trim($this->string('q')->value());

        return $search !== '' ? '%'.addcslashes($search, '\\%_').'%' : null;
    }
}
