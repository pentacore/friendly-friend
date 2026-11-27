<?php

declare(strict_types=1);

namespace App\Quotes\Discord;

use App\Access\AppSettings;
use App\Discord\Interactions\CustomId;
use App\Enums\AppSetting;
use App\Models\Quote;
use App\Models\QuoteLine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Discord message payloads for quotes: the private preview with
 * Save / Edit / Cancel, and the public post.
 */
final class QuoteMessages
{
    /** How many quotes `/quote search` lists. */
    public const int SEARCH_RESULTS = 5;

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
        $ttl = resolve(AppSettings::class)->get(AppSetting::DraftTtlMinutes);

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

    /**
     * `/quote search` results: a count (saying when they are only close
     * spellings), then each quote (shortened) with a
     * link to its page, kept under Discord's 2000 characters.
     *
     * @param  Collection<int, Quote>  $quotes
     * @return array<string, mixed>
     */
    public static function searchResults(string $text, Collection $quotes, int $total, bool $closeMatches = false): array
    {
        if ($quotes->isEmpty()) {
            return self::notice("Nothing matches “{$text}”. Try a name or a single word.");
        }

        $heading = match (true) {
            $closeMatches => $total === 1 ? '**No exact matches, 1 close one** for' : "**No exact matches, {$total} close ones** for",
            $total === 1 => '**1 quote matches**',
            default => "**{$total} quotes match**",
        };
        $more = $total > $quotes->count() ? "\nShowing the best {$quotes->count()}; see them all on the website: <".route('quotes.index', ['q' => $text]).'>' : '';
        $items = $quotes->map(fn (Quote $quote): string => Str::limit(self::quoteBody($quote, markNewPeople: false), 300)."\n<".route('quotes.show', $quote).'>');

        return self::notice(Str::limit("{$heading} “{$text}”\n\n".$items->implode("\n\n").$more, 1990));
    }

    /**
     * What the bot can do, for a mention that asks for help.
     *
     * @return array<string, mixed>
     */
    public static function help(): array
    {
        return self::notice(implode("\n", [
            '**What I can do**',
            '• Reply to a message and @mention me, or right-click it → Apps → **Save quote**, to save it as a quote.',
            '• **/quote add**, or @mention me with what was said ("@me Bo said …"), to save a quote you write yourself.',
            '• **/quote random** [person], or ask me for a random quote, to see one.',
            '• **/quote search** text [person], or ask me to find one ("@me find the one about IKEA"), to find quotes (only you see the results).',
            '• **/privacy** to opt out of server stats.',
            'Every saved quote is also on the website.',
        ]));
    }

    /**
     * One speaker: `"text" - Person`. A conversation: `**Name:** text` lines.
     */
    private static function quoteBody(Quote $quote, bool $markNewPeople): string
    {
        $mark = fn (QuoteLine $line): string => $markNewPeople && $line->isNewPerson() ? ' *(new person)*' : '';
        $first = $quote->lines->first();

        if ($first !== null && $quote->lines->every(fn (QuoteLine $line): bool => $line->speaker_name === $first->speaker_name)) {
            $text = $quote->lines->map(fn (QuoteLine $line): string => $line->text)->implode("\n");
            $body = "\"{$text}\" - {$first->speaker_name}".$mark($first);
        } else {
            $body = $quote->lines
                ->map(fn (QuoteLine $line): string => "**{$line->speaker_name}:**{$mark($line)} {$line->text}")
                ->implode("\n");
        }

        $meta = array_filter([
            $quote->context !== null ? "*{$quote->context}*" : null,
            $quote->said_at?->timezone(config()->string('quotes.timezone'))->isoFormat('D MMM YYYY'),
        ]);

        return $body.($meta !== [] ? "\n".implode(' · ', $meta) : '');
    }
}
