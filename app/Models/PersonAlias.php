<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PersonAliasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Another name a person goes by ("Marre" → Martin). Unique, case-insensitive.
 *
 * @property int $id
 * @property int $person_id
 * @property string $alias
 * @property-read Person $person
 */
#[Fillable(['person_id', 'alias'])]
class PersonAlias extends Model
{
    /** @use HasFactory<PersonAliasFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
