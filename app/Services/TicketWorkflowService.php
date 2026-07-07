<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Exceptions\InvalidTicketTransitionException;
use App\Models\MaintenanceTicket;
use App\Models\User;

/**
 * The single place a ticket's status ever changes (Phases.md Phase 7 DoD:
 * a drag-and-drop move and a manual status change must enforce the same
 * rule) — both the Kanban board and any future admin action call this,
 * never `$ticket->update(['status' => ...])` directly.
 */
class TicketWorkflowService
{
    /**
     * @throws InvalidTicketTransitionException
     */
    public function transition(MaintenanceTicket $ticket, TicketStatus $to, ?User $actor): void
    {
        $from = $ticket->status;

        if (! $from->canTransitionTo($to)) {
            throw new InvalidTicketTransitionException($from, $to);
        }

        $ticket->update(['status' => $to]);

        activity('patrimoine')
            ->causedBy($actor)
            ->performedOn($ticket)
            ->withProperties(['from' => $from->value, 'to' => $to->value])
            ->log('ticket_status_changed');
    }
}
