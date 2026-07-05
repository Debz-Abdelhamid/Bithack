<?php

namespace Database\Factories;

use App\Enums\BuildingStatus;
use App\Models\Building;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Building>
 */
class BuildingFactory extends Factory
{
    protected $model = Building::class;

    public function definition(): array
    {
        return [
            'faculty_id' => null,
            'code' => strtoupper($this->faker->unique()->bothify('BAT-##?')),
            'name' => 'Building '.$this->faker->unique()->word(),
            'campus' => 'Main Campus',
            'address' => $this->faker->streetAddress(),
            'floors_count' => $this->faker->numberBetween(1, 6),
            // Clustered around the UBMA campus center used by the map.
            'latitude' => 36.8133517 + $this->faker->randomFloat(5, -0.003, 0.003),
            'longitude' => 7.7198301 + $this->faker->randomFloat(5, -0.003, 0.003),
            'status' => BuildingStatus::Active,
        ];
    }
}
