<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Equipment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    public function definition(): array
    {
        return [
            'equipment_id' => Equipment::factory(),
            'local_id' => null,
            'service_id' => Service::factory(),
            'assigned_to_user_id' => null,
            'assigned_by_user_id' => User::factory(),
            'start_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'end_date' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
