<?php

namespace App\Observers;

use App\Jobs\SendTicketNotification;
use App\Models\MaintenanceTicket;
use App\Models\User;
use App\Support\RoleName;
use Illuminate\Database\Eloquent\Builder;

/**
 * Étape 4 — "Scan QR du bien → ticket créé automatiquement" (Phases.md
 * Phase 6). Assigns the human-readable reference and computes `sla_due_at`
 * at creation (Schema.md §4) so neither is ever set by hand; routes to
 * the equipment's current service (if any) when the caller didn't pick
 * one explicitly; notifies A3 + the routed service on creation.
 */
class MaintenanceTicketObserver
{
    public function creating(MaintenanceTicket $ticket): void
    {
        if (blank($ticket->reference)) {
            $ticket->reference = $this->nextReference();
        }

        if (blank($ticket->sla_due_at)) {
            $ticket->sla_due_at = $ticket->priority->slaDueAt(now());
        }

        if ($ticket->assigned_service_id === null && $ticket->equipment_id !== null) {
            $ticket->assigned_service_id = $ticket->equipment?->activeAssignment()?->service_id;
        }
    }

    public function created(MaintenanceTicket $ticket): void
    {
        $a3 = User::query()
            ->whereHas('roles', fn (Builder $query): Builder => $query->where('name', RoleName::GESTIONNAIRE_PATRIMOINE))
            ->first();

        if ($a3 !== null) {
            SendTicketNotification::dispatch(
                $a3->getKey(),
                __('patrimoine.tickets.notif_new_title'),
                __('patrimoine.tickets.notif_new_body', ['reference' => $ticket->reference]),
                $ticket->priority->value === 'urgent' ? 'danger' : 'info',
            );
        }

        $responsibleUserId = $ticket->assignedService?->responsible_user_id;

        if ($responsibleUserId !== null && $responsibleUserId !== $a3?->getKey()) {
            SendTicketNotification::dispatch(
                $responsibleUserId,
                __('patrimoine.tickets.notif_new_title'),
                __('patrimoine.tickets.notif_new_body', ['reference' => $ticket->reference]),
                $ticket->priority->value === 'urgent' ? 'danger' : 'info',
            );
        }
    }

    /**
     * Sequential per-year display code (TCK-YYYY-NNNNN), same pattern as
     * EquipmentObserver's inventory codes.
     */
    private function nextReference(): string
    {
        $prefix = 'TCK-'.now()->format('Y').'-';

        $last = MaintenanceTicket::query()
            ->withoutGlobalScopes()
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')
            ->lockForUpdate()
            ->value('reference');

        $next = is_string($last) ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
