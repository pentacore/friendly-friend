<?php

declare(strict_types=1);

namespace App\Quotes\Discord;

use App\Discord\Interactions\CustomId;
use App\Models\Quote;
use App\Models\QuoteLine;

/**
 * Discord message payloads for quotes: the private preview with
 * Save / Edit / Cancel, and the public post.
 */
final class QuoteMessages
{
    private const int EPHEMERAL = 64;

    private const int SUCCESS = 3;

    private const int PRIMARY = 1;

    private const int DANGER = 4;

    /**
     * @return array<string, mixed>
     */
    public static function preview(Quote $quote, ?string $notice = null): array
    {
        $body = self::quoteBody($quote, markNewPeople: true);
        $ttl = config()->integer('quotes.draft_ttl_minutes');

        return [
            'content' => trim(($notice !== null ? "{$notice}\n\n" : '')."**Preview** (only you can see this; expires in {$ttl} min)\n{$body}"),
            'components' => [[
                'type' => 1,
                'components' => [
                    ['type' => 2, 'style' => self::SUCCESS, 'label' => 'Save', 'custom_id' => CustomId::for(CustomId::SAVE, $quote->id)],
                    ['type' => 2, 'style' => self::PRIMARY, 'label' => 'Edit', 'custom_id' => CustomId::for(CustomId::EDIT, $quote->id)],
                    ['type' => 2, 'style' => self::DANGER, 'label' => 'Cancel', 'custom_id' => CustomId::for(CustomId::CANCEL, $quote->id)],
                ],
            ]],
        ];
    }

    /**
     * The quote as posted for everyone.
     *
     * @return array<string, mixed>
     */
    public static function published(Quote $quote, string $footer = ''): array
    {
        return [
            'content' => trim(self::quoteBody($quote, markNewPeople: false)."\n".$footer),
            'components' => [],
        ];
    }

    /**
     * A plain notice that replaces the preview (and removes its buttons).
     *
     * @return array<string, mixed>
     */
    public static function notice(string $text, bool $ephemeral = false): array
    {
        return ['content' => $text, 'components' => [], ...($ephemeral ? ['flags' => self::EPHEMERAL] : [])];
    }

    private static function quoteBody(Quote $quote, bool $markNewPeople): string
    {
        $lines = $quote->lines->map(function (QuoteLine $line) use ($markNewPeople): string {
            $speaker = $line->speaker_name.($markNewPeople && $line->isNewPerson() ? ' *(new person)*' : '');
            $text = str_replace("\n", "\n> ", $line->text);

            return "> **{$speaker}:** {$text}";
        })->implode("\n");

        $meta = array_filter([
            $quote->context !== null ? "*{$quote->context}*" : null,
            $quote->said_at?->timezone(config()->string('quotes.timezone'))->isoFormat('D MMM YYYY'),
        ]);

        return $lines.($meta !== [] ? "\n".implode(' · ', $meta) : '');
    }
}
