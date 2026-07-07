<?php

namespace App\Exceptions;

use App\Enums\TicketStatus;
use RuntimeException;

/**
 * Thrown by TicketWorkflowService for any illegal status move — the same
 * exception whether the move came from the Kanban drag or a manual
 * status-change action, so both surfaces enforce one rule (Phases.md
 * Phase 7 DoD: "enforce the same validation as a manual status change").
 */
class InvalidTicketTransitionException extends RuntimeException
{
    public function __construct(TicketStatus $from, TicketStatus $to)
    {
        parent::__construct(__('patrimoine.tickets.invalid_transition', [
            'from' => $from->getLabel(),
            'to' => $to->getLabel(),
        ]));
    }
}
