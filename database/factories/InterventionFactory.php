<?php

namespace Database\Factories;

use App\Enums\InterventionStatus;
use App\Models\Intervention;
use App\Models\MaintenanceTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Intervention>
 */
class InterventionFactory extends Factory
{
    protected $model = Intervention::class;

    public function definition(): array
    {
        return [
            'maintenance_ticket_id' => MaintenanceTicket::factory(),
            'technician_id' => null,
            'scheduled_at' => null,
            'completed_at' => null,
            'report' => null,
            'cost' => null,
            'status' => InterventionStatus::Planned,
        ];
    }
}
