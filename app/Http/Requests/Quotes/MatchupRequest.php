<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotes;

use App\Models\Quote;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * A this-or-that pick: two quotes the user can see, and which one won.
 */
final class MatchupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quotes = Quote::query()->whereKey([$this->integer('quote_a'), $this->integer('quote_b')])->get();

        return $quotes->count() === 2
            && $quotes->every(fn (Quote $quote): bool => Gate::forUser($this->user())->allows('view', $quote));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quote_a' => ['required', 'integer', 'different:quote_b'],
            'quote_b' => ['required', 'integer'],
            'winner' => ['required', 'integer', Rule::in([$this->integer('quote_a'), $this->integer('quote_b')])],
        ];
    }
}
