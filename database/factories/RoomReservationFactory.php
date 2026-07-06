<?php

namespace Database\Factories;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Local;
use App\Models\RoomReservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomReservation>
 */
class RoomReservationFactory extends Factory
{
    protected $model = RoomReservation::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 day', '+2 months');

        return [
            'local_id' => Local::factory(),
            'source' => ReservationSource::Request,
            'requested_by_user_id' => User::factory(),
            'teacher_user_id' => null,
            'purpose' => fake()->sentence(),
            'start_at' => $start,
            'end_at' => (clone $start)->modify('+90 minutes'),
            'status' => ReservationStatus::Pending,
        ];
    }

    public function timetable(): static
    {
        return $this->state(fn (): array => [
            'source' => ReservationSource::Timetable,
            'status' => ReservationStatus::Confirmed,
            'module_name' => fake()->words(2, true),
            'level' => 'l1',
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => ['status' => ReservationStatus::Confirmed]);
    }
}
