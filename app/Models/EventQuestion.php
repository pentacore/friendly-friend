<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuestionKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Something organisers ask everyone coming ("Will you eat crayfish?").
 *
 * @property int $id
 * @property int $event_id
 * @property int $position
 * @property string $prompt
 * @property QuestionKind $kind
 * @property list<string> $options choices, for QuestionKind::Choice
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, EventQuestionAnswer> $answers
 */
#[Fillable(['event_id', 'position', 'prompt', 'kind', 'options'])]
class EventQuestion extends Model
{
    /**
     * @return HasMany<EventQuestionAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(EventQuestionAnswer::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * The answers this question accepts (null: any text).
     *
     * @return list<string>|null
     */
    public function allowedValues(): ?array
    {
        return match ($this->kind) {
            QuestionKind::YesNo => ['yes', 'no'],
            QuestionKind::Choice => $this->options,
            QuestionKind::Text => null,
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => QuestionKind::class,
            'options' => 'array',
            'position' => 'integer',
        ];
    }
}
