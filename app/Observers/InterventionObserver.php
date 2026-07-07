<?php

namespace App\Observers;

use App\Jobs\SendTicketNotification;
use App\Models\Intervention;

/**
 * Phases.md Phase 7: "Technician notified when an intervention is
 * assigned to them."
 */
class InterventionObserver
{
    public function created(Intervention $intervention): void
    {
        $this->notifyIfAssigned($intervention);
    }

    public function updated(Intervention $intervention): void
    {
        if ($intervention->wasChanged('technician_id')) {
            $this->notifyIfAssigned($intervention);
        }
    }

    private function notifyIfAssigned(Intervention $intervention): void
    {
        if ($intervention->technician_id === null) {
            return;
        }

        SendTicketNotification::dispatch(
            $intervention->technician_id,
            __('patrimoine.tickets.notif_assigned_title'),
            __('patrimoine.tickets.notif_assigned_body', ['reference' => $intervention->maintenanceTicket->reference]),
            'info',
        );
    }
}
