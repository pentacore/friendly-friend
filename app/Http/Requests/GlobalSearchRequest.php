<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The header search box (Ctrl+K).
 */
final class GlobalSearchRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /** @query */
            'q' => ['required', 'string', 'max:100'],
        ];
    }

    public function search(): string
    {
        return trim($this->string('q')->value());
    }

    /**
     * An `ilike` pattern for the search, with `%` and `_` taken literally.
     */
    public function pattern(): string
    {
        return '%'.addcslashes($this->search(), '\\%_').'%';
    }
}
