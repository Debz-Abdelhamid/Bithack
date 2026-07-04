<?php

namespace Database\Factories;

use App\Enums\ServiceType;
use App\Models\Faculty;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'faculty_id' => Faculty::factory(),
            'name' => 'Service '.$this->faker->unique()->words(2, true),
            'type' => $this->faker->randomElement(ServiceType::cases()),
            'responsible_user_id' => null,
        ];
    }
}
