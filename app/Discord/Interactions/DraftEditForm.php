<?php

declare(strict_types=1);

namespace App\Discord\Interactions;

use App\Ai\CaptureInput;
use App\Ai\PlainQuoteNormalizer;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Quotes\Data\NormalizedQuote;
use Illuminate\Support\Facades\Cache;

/**
 * The "Edit" form (Discord modal) of a draft. The bot must answer an Edit
 * click with the form itself within 3 seconds, without touching the
 * database, so the form is built whenever a preview is shown and kept in
 * the cache until the draft expires.
 */
final readonly class DraftEditForm
{
    public function __construct(private PlainQuoteNormalizer $plain) {}

    public function remember(Quote $quote): void
    {
        Cache::put(self::key($quote->id), $this->modal($quote), now()->addMinutes(config()->integer('quotes.draft_ttl_minutes') + 5));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function cached(int $quoteId): ?array
    {
        $modal = Cache::get(self::key($quoteId));

        if (! is_array($modal)) {
            return null;
        }

        $form = [];

        foreach ($modal as $key => $value) {
            $form[(string) $key] = $value;
        }

        return $form;
    }

    public static function forget(int $quoteId): void
    {
        Cache::forget(self::key($quoteId));
    }

    /**
     * Read a submitted form back into quote content.
     *
     * @param  array<string, string>  $fields  custom_id → value
     */
    public function parse(array $fields): NormalizedQuote
    {
        return $this->plain->normalize(new CaptureInput(
            text: $fields['lines'] ?? '',
            context: $fields['context'] ?? null,
            when: $fields['when'] ?? null,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function modal(Quote $quote): array
    {
        $first = $quote->lines->first();
        $oneSpeaker = $first !== null && $quote->lines->every(fn (QuoteLine $line): bool => $line->speaker_name === $first->speaker_name);

        // Same format as the posted quote: `"text" - Person`, or "Name: text" lines.
        $lines = $oneSpeaker
            ? '"'.$quote->lines->map(fn (QuoteLine $line): string => $line->text)->implode("\n").'" - '.$first->speaker_name
            : $quote->lines
                ->map(fn (QuoteLine $line): string => $line->speaker_name.': '.str_replace("\n", ' / ', $line->text))
                ->implode("\n");

        return [
            'custom_id' => CustomId::for(CustomId::MODAL, $quote->id),
            'title' => 'Edit quote',
            'components' => [
                $this->input('lines', '"Quote" - Person, or Name: text lines', 2, $lines, true, 4000),
                $this->input('context', 'Context (optional)', 1, (string) $quote->context, false, 300),
                $this->input('when', 'When (e.g. yesterday, 2026-10-31)', 1, $quote->said_at?->toDateString() ?? '', false, 80),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function input(string $id, string $label, int $style, string $value, bool $required, int $maxLength): array
    {
        return [
            'type' => 1,
            'components' => [[
                'type' => 4,
                'custom_id' => $id,
                'label' => mb_substr($label, 0, 45),
                'style' => $style,
                'value' => mb_substr($value, 0, $maxLength),
                'required' => $required,
                'max_length' => $maxLength,
            ]],
        ];
    }

    private static function key(int $quoteId): string
    {
        return "quote-draft-edit-form:{$quoteId}";
    }
}
