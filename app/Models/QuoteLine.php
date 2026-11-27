<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QuoteLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a quote. While a quote is a draft the speaker may not exist as
 * a Person yet (person_id null): `speaker_name` (and, for Discord users,
 * `speaker_discord_user_id`) say who it will be once saved.
 *
 * @property int $id
 * @property int $quote_id
 * @property int $position
 * @property int|null $person_id
 * @property int|null $speaker_discord_user_id
 * @property string $speaker_name
 * @property string $text
 * @property-read Person|null $person
 */
#[Fillable(['quote_id', 'position', 'person_id', 'speaker_discord_user_id', 'speaker_name', 'text'])]
class QuoteLine extends Model
{
    /** @use HasFactory<QuoteLineFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function isNewPerson(): bool
    {
        return $this->person_id === null && $this->speaker_discord_user_id === null;
    }
}
