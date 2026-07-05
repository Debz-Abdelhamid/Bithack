<?php

namespace Database\Factories;

use App\Enums\EquipmentCondition;
use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
{
    protected $model = Equipment::class;

    public function definition(): array
    {
        [$category, $designation] = fake()->randomElement([
            ['informatique', 'Desktop computer'],
            ['informatique', 'Laptop'],
            ['informatique', 'Video projector'],
            ['mobilier', 'Office desk'],
            ['mobilier', 'Lecture chair'],
            ['electrique', 'Air conditioner'],
        ]);

        return [
            // inventory_code intentionally omitted — the observer generates it.
            'designation' => $designation,
            'category' => $category,
            'sub_category' => null,
            'brand' => fake()->optional()->company(),
            'model' => fake()->optional()->bothify('Model-##??'),
            'serial_number' => fake()->optional()->bothify('SN-########'),
            'local_id' => null,
            'acquisition_date' => fake()->optional()->dateTimeBetween('-10 years'),
            'acquisition_value' => fake()->optional()->randomFloat(2, 5_000, 900_000),
            'warranty_end_date' => null,
            'status' => EquipmentStatus::InService,
            'condition' => EquipmentCondition::Good,
        ];
    }
}
