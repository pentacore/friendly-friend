<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MealSlot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * One meal of an event's meal plan, with who cooks it.
 *
 * @property int $id
 * @property int $event_id
 * @property Carbon $date
 * @property MealSlot $slot
 * @property string $title
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, DiscordUser> $cooks
 */
#[Fillable(['event_id', 'date', 'slot', 'title', 'notes'])]
class EventMeal extends Model
{
    /**
     * @return BelongsToMany<DiscordUser, $this>
     */
    public function cooks(): BelongsToMany
    {
        return $this->belongsToMany(DiscordUser::class, 'event_meal_cooks');
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
            'date' => 'date',
            'slot' => MealSlot::class,
        ];
    }
}
