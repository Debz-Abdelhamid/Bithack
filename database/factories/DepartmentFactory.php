<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Faculty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'faculty_id' => Faculty::factory(),
            'name' => 'Department of '.$this->faker->unique()->words(2, true),
            'code' => strtoupper($this->faker->unique()->lexify('DEP-???')),
        ];
    }
}
