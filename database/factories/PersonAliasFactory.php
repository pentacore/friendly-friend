<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Person;
use App\Models\PersonAlias;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonAlias>
 */
class PersonAliasFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'alias' => fake()->unique()->userName(),
        ];
    }
}
