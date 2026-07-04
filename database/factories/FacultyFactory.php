<?php

namespace Database\Factories;

use App\Models\Faculty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faculty>
 */
class FacultyFactory extends Factory
{
    protected $model = Faculty::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('FAC-???')),
            'name' => 'Faculté '.$this->faker->unique()->words(2, true),
        ];
    }
}
