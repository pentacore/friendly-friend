<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotes;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters of the quote feed (query string).
 */
final class QuoteIndexRequest extends FormRequest
{
    /** Best match (only with a search), newest first, or highest score. */
    public const array SORTS = ['best', 'newest', 'top'];

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
            /** @query */
            'person' => ['nullable', 'string', 'max:120'],
            /** @query */
            'sort' => ['nullable', Rule::in(self::SORTS)],
        ];
    }

    /**
     * The chosen order: best match by default while searching, else newest.
     */
    public function sort(): string
    {
        $search = trim($this->string('q')->value()) !== '';
        $sort = $this->string('sort')->value();

        return match (true) {
            $sort === 'top', $sort === 'newest' => $sort,
            $search => 'best',
            default => 'newest',
        };
    }
}
