<?php

declare(strict_types=1);

namespace App\Http\Requests\Stats;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters of /stats (query string): one server or all, and how many days.
 */
final class StatsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /** @query */
            'guild' => ['nullable', 'integer'],
            /** @query */
            'days' => ['nullable', 'integer', 'in:7,30,365'],
        ];
    }

    public function days(): int
    {
        return $this->integer('days', 30);
    }
}
