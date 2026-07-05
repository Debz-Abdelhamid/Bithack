<?php

namespace Database\Factories;

use App\Enums\LocalStatus;
use App\Enums\LocalType;
use App\Models\Building;
use App\Models\Local;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Local>
 */
class LocalFactory extends Factory
{
    protected $model = Local::class;

    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'code' => strtoupper($this->faker->unique()->bothify('LOC-###?')),
            'name' => 'Room '.$this->faker->unique()->numberBetween(1, 999),
            'type' => $this->faker->randomElement(LocalType::cases()),
            'floor' => $this->faker->numberBetween(0, 5),
            'capacity' => $this->faker->numberBetween(10, 300),
            'surface_m2' => $this->faker->randomFloat(2, 15, 400),
            'responsible_user_id' => null,
            'status' => LocalStatus::Available,
        ];
    }
}
