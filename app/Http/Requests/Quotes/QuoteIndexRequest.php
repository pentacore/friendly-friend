<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotes;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters of the quote feed (query string).
 */
final class QuoteIndexRequest extends FormRequest
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
            /** @query */
            'person' => ['nullable', 'string', 'max:120'],
        ];
    }
}
