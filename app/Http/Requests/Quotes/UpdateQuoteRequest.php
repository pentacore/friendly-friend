<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotes;

use App\Enums\SaidAtPrecision;
use App\Models\Quote;
use App\Quotes\Data\NormalizedLine;
use App\Quotes\Data\NormalizedQuote;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Editing a saved quote on the website; turns the form into NormalizedQuote content.
 */
final class UpdateQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quote = $this->route('quote');

        return $quote instanceof Quote && ($this->user()?->can('update', $quote) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:20'],
            'lines.*.speaker' => ['required', 'string', 'max:80'],
            'lines.*.text' => ['required', 'string', 'max:2000'],
            'context' => ['nullable', 'string', 'max:300'],
            'said_at' => ['nullable', 'date_format:Y-m-d'],
            'said_at_precision' => ['nullable', Rule::enum(SaidAtPrecision::class)],
        ];
    }

    public function content(): NormalizedQuote
    {
        $lines = [];

        foreach ($this->array('lines') as $line) {
            if (is_array($line) && is_string($line['speaker'] ?? null) && is_string($line['text'] ?? null)) {
                $lines[] = new NormalizedLine(trim($line['speaker']), trim($line['text']));
            }
        }

        $saidAt = $this->filled('said_at')
            ? CarbonImmutable::createFromFormat('Y-m-d', $this->string('said_at')->value(), config()->string('quotes.timezone'))?->startOfDay()
            : null;

        return new NormalizedQuote(
            lines: $lines,
            context: $this->filled('context') ? trim($this->string('context')->value()) : null,
            saidAt: $saidAt ?: null,
            saidAtPrecision: $saidAt instanceof CarbonImmutable
                ? ($this->enum('said_at_precision', SaidAtPrecision::class) ?? SaidAtPrecision::Day)
                : null,
        );
    }
}
