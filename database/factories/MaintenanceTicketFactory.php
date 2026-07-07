<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Models\Equipment;
use App\Models\MaintenanceTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceTicket>
 */
class MaintenanceTicketFactory extends Factory
{
    protected $model = MaintenanceTicket::class;

    public function definition(): array
    {
        return [
            // reference / sla_due_at intentionally omitted — the observer computes them.
            'equipment_id' => Equipment::factory(),
            'local_id' => null,
            'reported_by_user_id' => User::factory(),
            'source' => TicketSource::Manual,
            'description' => fake()->sentence(12),
            'priority' => TicketPriority::Standard,
            'category' => fake()->randomElement(['informatique', 'mobilier', 'electrique', 'plomberie']),
            'status' => TicketStatus::New,
            'assigned_service_id' => null,
        ];
    }

    public function qrScan(): static
    {
        return $this->state(fn (): array => [
            'source' => TicketSource::QrScan,
            'priority' => TicketPriority::Urgent,
        ]);
    }
}
