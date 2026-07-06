<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicTerm>
 */
class AcademicTermFactory extends Factory
{
    protected $model = AcademicTerm::class;

    public function definition(): array
    {
        $year = $this->faker->unique()->numberBetween(2020, 2100);
        $semester = $this->faker->numberBetween(1, 2);

        return [
            'academic_year' => "{$year}-".($year + 1),
            'semester' => $semester,
            'start_date' => $semester === 1 ? "{$year}-09-15" : ($year + 1).'-02-01',
            'end_date' => $semester === 1 ? ($year + 1).'-01-25' : ($year + 1).'-06-20',
        ];
    }
}
