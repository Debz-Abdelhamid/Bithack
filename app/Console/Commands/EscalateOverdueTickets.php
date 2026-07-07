<?php

namespace App\Console\Commands;

use App\Enums\TicketStatus;
use App\Jobs\SendTicketNotification;
use App\Models\Equipment;
use App\Models\Local;
use App\Models\MaintenanceTicket;
use App\Models\Scopes\FacultyScope;
use App\Models\User;
use App\Support\RoleName;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Phases.md Phase 7 DoD: "an approaching/breached SLA visibly escalates
 * (notification + dashboard flag) without manual polling." Scheduled every
 * 15 minutes (routes/console.php). `escalated_at` is the idempotency guard
 * — a ticket is notified-as-escalated at most once, not on every tick.
 *
 * The "80% elapsed" check runs in PHP, not SQL — `sla_due_at - created_at`
 * interval arithmetic isn't portable between Postgres and the sqlite test
 * driver, and the candidate set (open, not-yet-escalated tickets) is small.
 */
class EscalateOverdueTickets extends Command
{
    protected $signature = 'patrimo:escalate-tickets';

    protected $description = 'Notify A3 + the routed N2 for tickets approaching or past their SLA deadline';

    public function handle(): int
    {
        $candidates = MaintenanceTicket::query()
            ->withoutGlobalScopes()
            ->whereNotIn('status', [TicketStatus::Resolved, TicketStatus::Closed, TicketStatus::Cancelled])
            ->whereNull('escalated_at')
            ->get();

        $escalated = $candidates->filter(fn (MaintenanceTicket $ticket): bool => $this->needsEscalation($ticket));

        foreach ($escalated as $ticket) {
            $this->escalate($ticket);
        }

        $this->components->info("Escalated {$escalated->count()} ticket(s).");

        return self::SUCCESS;
    }

    private function needsEscalation(MaintenanceTicket $ticket): bool
    {
        $now = now();

        if ($now->greaterThanOrEqualTo($ticket->sla_due_at)) {
            return true;
        }

        $totalSeconds = $ticket->created_at->diffInSeconds($ticket->sla_due_at);
        $elapsedSeconds = $ticket->created_at->diffInSeconds($now);

        return $totalSeconds > 0 && ($elapsedSeconds / $totalSeconds) >= 0.8;
    }

    private function escalate(MaintenanceTicket $ticket): void
    {
        $breached = now()->greaterThanOrEqualTo($ticket->sla_due_at);

        $ticket->update(['escalated_at' => now()]);

        $title = $breached
            ? __('patrimoine.tickets.notif_breached_title')
            : __('patrimoine.tickets.notif_approaching_title');

        $body = __('patrimoine.tickets.notif_escalation_body', ['reference' => $ticket->reference]);

        foreach ($this->recipients($ticket) as $recipient) {
            SendTicketNotification::dispatch($recipient->getKey(), $title, $body, $breached ? 'danger' : 'warning');
        }
    }

    /**
     * The routed N2 (via the ticket's equipment/local → building's
     * faculty) plus A3 — mirrors ReservationApprovalService::
     * resolveApprover's precedent, queried without global scopes since
     * this runs outside any authenticated request.
     *
     * @return list<User>
     */
    private function recipients(MaintenanceTicket $ticket): array
    {
        $recipients = [];

        $a3 = User::query()
            ->whereHas('roles', fn (Builder $query): Builder => $query->where('name', RoleName::GESTIONNAIRE_PATRIMOINE))
            ->first();

        if ($a3 !== null) {
            $recipients[] = $a3;
        }

        $facultyId = $this->unscopedLocal($ticket)?->building?->faculty_id;

        if ($facultyId !== null) {
            $n2 = User::query()
                ->where('faculty_id', $facultyId)
                ->whereHas('roles', fn (Builder $query): Builder => $query->where('name', RoleName::RESPONSABLE_FACULTE))
                ->first();

            if ($n2 !== null) {
                $recipients[] = $n2;
            }
        }

        return $recipients;
    }

    private function unscopedLocal(MaintenanceTicket $ticket): ?Local
    {
        $localId = $ticket->local_id ?? Equipment::query()
            ->withoutGlobalScope(FacultyScope::class)
            ->whereKey($ticket->equipment_id)
            ->value('local_id');

        if ($localId === null) {
            return null;
        }

        return Local::query()
            ->withoutGlobalScope(FacultyScope::class)
            ->with(['building' => fn (Relation $query): Relation => $query->withoutGlobalScope(FacultyScope::class)])
            ->find($localId);
    }
}
