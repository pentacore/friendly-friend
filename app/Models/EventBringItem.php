<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Something an event needs people to bring, and how many.
 *
 * @property int $id
 * @property int $event_id
 * @property string $label
 * @property int $quantity_needed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, EventBringClaim> $claims
 */
#[Fillable(['event_id', 'label', 'quantity_needed'])]
class EventBringItem extends Model
{
    /**
     * @return HasMany<EventBringClaim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(EventBringClaim::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_needed' => 'integer',
        ];
    }
}
