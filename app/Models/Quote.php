<?php

declare(strict_types=1);

namespace App\Models;

use App\Access\AccessSnapshot;
use App\Enums\QuoteSource;
use App\Enums\QuoteStatus;
use App\Enums\SaidAtPrecision;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $guild_id
 * @property QuoteStatus $status
 * @property QuoteSource $source
 * @property string|null $context
 * @property Carbon|null $said_at
 * @property SaidAtPrecision|null $said_at_precision
 * @property string|null $source_message_id
 * @property string|null $source_channel_id
 * @property string|null $source_message_url
 * @property int|null $import_id
 * @property int $submitted_by_discord_user_id
 * @property string $raw_input
 * @property array<string, mixed>|null $ai_output
 * @property int $score
 * @property int $elo
 * @property int $matchup_count
 * @property string $search_text
 * @property Carbon|null $published_at
 * @property string|null $preview_token interaction token of a draft's private preview (encrypted)
 * @property string|null $preview_channel_id DM channel of a draft's preview
 * @property string|null $preview_message_id DM message of a draft's preview
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Guild $guild
 * @property-read DiscordUser $submittedBy
 * @property-read QuoteVote|null $myVote
 * @property-read Collection<int, QuoteLine> $lines
 */
#[Fillable(['guild_id', 'status', 'source', 'context', 'said_at', 'said_at_precision', 'source_message_id', 'source_channel_id', 'source_message_url', 'import_id', 'submitted_by_discord_user_id', 'raw_input', 'ai_output', 'published_at', 'preview_token', 'preview_channel_id', 'preview_message_id'])]
#[Hidden(['preview_token', 'preview_channel_id', 'preview_message_id'])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    /** Shorter words only match exactly: a typo in "bo" or "fix" finds too much. */
    public const int CLOSE_MATCH_LENGTH = 4;

    /**
     * @return BelongsTo<Guild, $this>
     */
    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    /**
     * @return BelongsTo<DiscordUser, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(DiscordUser::class, 'submitted_by_discord_user_id');
    }

    /**
     * @return HasMany<QuoteLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('position');
    }

    /**
     * The current user's vote; constrain it when eager loading
     * (`with(['myVote' => fn ($q) => $q->where('user_id', $id)])`).
     *
     * @return HasOne<QuoteVote, $this>
     */
    public function myVote(): HasOne
    {
        return $this->hasOne(QuoteVote::class);
    }

    /**
     * @return HasMany<QuoteVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(QuoteVote::class);
    }

    /**
     * Published quotes of guilds the user can see.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, AccessSnapshot $access): void
    {
        $query->where('status', QuoteStatus::Published)->whereIn('guild_id', $access->guildIds);
    }

    /**
     * The shared quote search (feed, person pages, Discord). Every word must
     * match, as a prefix so it works while typing, ignoring case and accents;
     * `-word` leaves quotes with it out. A word matches the quote's text
     * (`search_vector`: speakers as written, lines, context) or the current
     * name or a nickname of a person on one of its lines. With
     * `$closeMatches`, a word of at least {@see self::CLOSE_MATCH_LENGTH}
     * letters also matches a close spelling (trigram `word_similarity`), for
     * typos; {@see self::applySearch()} turns that on only when nothing
     * matches exactly. Blank or symbol-only text matches everything.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $text, bool $closeMatches = false): void
    {
        ['include' => $include, 'exclude' => $exclude] = self::searchTerms($text);

        foreach ($include as $term) {
            $word = substr($term, 0, -2);
            $close = $closeMatches && mb_strlen($word) >= self::CLOSE_MATCH_LENGTH;

            $query->where(fn (Builder $matches) => $matches
                ->whereRaw("quotes.search_vector @@ to_tsquery('simple', f_unaccent(?))", [$term])
                ->when($close, fn (Builder $typos) => $typos->orWhereRaw('f_unaccent(?) <% f_unaccent(lower(quotes.search_text))', [$word]))
                ->orWhereExists(fn (QueryBuilder $people) => $people
                    ->selectRaw('1')
                    ->from('quote_lines')
                    ->join('people', 'people.id', '=', 'quote_lines.person_id')
                    ->whereColumn('quote_lines.quote_id', 'quotes.id')
                    ->where(fn (QueryBuilder $names) => $names
                        ->whereRaw("to_tsvector('simple', f_unaccent(people.display_name)) @@ to_tsquery('simple', f_unaccent(?))", [$term])
                        ->when($close, fn (QueryBuilder $typos) => $typos->orWhereRaw('f_unaccent(?) <% f_unaccent(lower(people.display_name))', [$word]))
                        ->orWhereExists(fn (QueryBuilder $aliases) => $aliases
                            ->selectRaw('1')
                            ->from('person_aliases')
                            ->whereColumn('person_aliases.person_id', 'people.id')
                            ->where(fn (QueryBuilder $alias) => $alias
                                ->whereRaw("to_tsvector('simple', f_unaccent(person_aliases.alias)) @@ to_tsquery('simple', f_unaccent(?))", [$term])
                                ->when($close, fn (QueryBuilder $typos) => $typos->orWhereRaw('f_unaccent(?) <% f_unaccent(lower(person_aliases.alias))', [$word])))))));
        }

        foreach ($exclude as $term) {
            $query->whereRaw("not (quotes.search_vector @@ to_tsquery('simple', f_unaccent(?)))", [$term]);
        }
    }

    /**
     * Searches the query, falling back to close matches (typos) when no quote
     * in it matches exactly, so close spellings never crowd out exact hits.
     * Returns whether it fell back.
     *
     * @param  Builder<self>  $query
     */
    public static function applySearch(Builder $query, string $text): bool
    {
        $closeMatches = self::searchTerms($text)['include'] !== [] && ! $query->clone()->search($text)->exists();
        $query->search($text, $closeMatches);

        return $closeMatches;
    }

    /**
     * Best match first: quotes whose text has more of the words, more often
     * (`ts_rank`), then the closest spelling (for close matches). Words
     * matched only through a person's name rank zero here but still pass
     * the `search` scope.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function orderByRelevance(Builder $query, string $text): void
    {
        $include = self::searchTerms($text)['include'];

        if ($include !== []) {
            $query->orderByRaw("ts_rank(quotes.search_vector, to_tsquery('simple', f_unaccent(?))) desc", [implode(' | ', $include)])
                ->orderByRaw('word_similarity(f_unaccent(?), f_unaccent(lower(quotes.search_text))) desc', [implode(' ', array_map(fn (string $term): string => substr($term, 0, -2), $include))]);
        }
    }

    /**
     * The words of a search as prefix tsquery terms (`word:*`), split into
     * words to match and words to leave out. Only letters and digits are
     * kept, so the terms are always valid tsquery syntax.
     *
     * @return array{include: list<string>, exclude: list<string>}
     */
    public static function searchTerms(string $text): array
    {
        $terms = ['include' => [], 'exclude' => []];

        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            $list = str_starts_with($word, '-') ? 'exclude' : 'include';

            foreach (preg_split('/[^\p{L}\p{N}]+/u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
                $terms[$list][] = mb_strtolower($part).':*';
            }
        }

        return ['include' => array_values(array_unique($terms['include'])), 'exclude' => array_values(array_unique($terms['exclude']))];
    }

    /**
     * Rebuild the full-text search source from speakers, lines and context.
     */
    public function refreshSearchText(): void
    {
        $this->unsetRelation('lines');

        $parts = $this->lines
            ->flatMap(fn (QuoteLine $line): array => [$line->speaker_name, $line->text])
            ->all();

        $this->forceFill(['search_text' => implode(' ', array_filter([...$parts, $this->context]))])->saveQuietly();
    }

    /**
     * @return HasMany<QuoteRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(QuoteRevision::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'source' => QuoteSource::class,
            'said_at' => 'datetime',
            'said_at_precision' => SaidAtPrecision::class,
            'ai_output' => 'array',
            'preview_token' => 'encrypted',
            'published_at' => 'datetime',
        ];
    }
}
