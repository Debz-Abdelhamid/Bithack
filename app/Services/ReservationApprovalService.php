<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Jobs\SendReservationNotification;
use App\Models\Local;
use App\Models\RoomReservation;
use App\Models\Scopes\FacultyScope;
use App\Models\User;
use App\Support\RoleName;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * Confirm/reject a pending `request` row (Phase 5 DoD). Centralized here
 * so the admin table action and any future entry point (e.g. an API)
 * share one source of truth for the auto-reject-conflicts rule
 * (PROGRESS.md open question #5 default).
 */
class ReservationApprovalService
{
    public function confirm(RoomReservation $reservation, ?User $actor): void
    {
        if ($reservation->status !== ReservationStatus::Pending) {
            return;
        }

        if (RoomReservation::hasConfirmedOverlap(
            $reservation->local_id,
            $reservation->start_at,
            $reservation->end_at,
            $reservation->getKey(),
        )) {
            Notification::make()
                ->danger()
                ->title(__('patrimoine.reservations.confirm_blocked'))
                ->send();

            return;
        }

        if ($reservation->department_id !== null && RoomReservation::hasConfirmedGroupOverlap(
            $reservation->department_id,
            $reservation->level,
            $reservation->student_group,
            $reservation->start_at,
            $reservation->end_at,
            $reservation->getKey(),
        )) {
            Notification::make()
                ->danger()
                ->title(__('patrimoine.reservations.confirm_blocked_group'))
                ->send();

            return;
        }

        $conflicts = RoomReservation::conflictingPending(
            $reservation->local_id,
            $reservation->start_at,
            $reservation->end_at,
            $reservation->getKey(),
        );

        DB::transaction(function () use ($reservation, $actor, $conflicts): void {
            $reservation->update([
                'status' => ReservationStatus::Confirmed,
                'approved_by_user_id' => $actor?->getKey(),
            ]);

            foreach ($conflicts as $conflict) {
                $conflict->update([
                    'status' => ReservationStatus::Rejected,
                    'approved_by_user_id' => $actor?->getKey(),
                ]);
            }
        });

        SendReservationNotification::dispatch(
            $reservation->requested_by_user_id,
            __('patrimoine.reservations.notif_confirmed_title'),
            __('patrimoine.reservations.notif_confirmed_body', ['local' => $this->unscopedLocal($reservation)->code]),
            'success',
        );

        foreach ($conflicts as $conflict) {
            SendReservationNotification::dispatch(
                $conflict->requested_by_user_id,
                __('patrimoine.reservations.notif_auto_rejected_title'),
                __('patrimoine.reservations.notif_auto_rejected_body', ['local' => $this->unscopedLocal($conflict)->code]),
                'warning',
            );
        }
    }

    public function reject(RoomReservation $reservation, ?User $actor, ?string $reason): void
    {
        if ($reservation->status !== ReservationStatus::Pending) {
            return;
        }

        $reservation->update([
            'status' => ReservationStatus::Rejected,
            'approved_by_user_id' => $actor?->getKey(),
        ]);

        SendReservationNotification::dispatch(
            $reservation->requested_by_user_id,
            __('patrimoine.reservations.notif_rejected_title'),
            filled($reason)
                ? __('patrimoine.reservations.notif_rejected_body_with_reason', ['local' => $this->unscopedLocal($reservation)->code, 'reason' => $reason])
                : __('patrimoine.reservations.notif_rejected_body', ['local' => $this->unscopedLocal($reservation)->code]),
            'danger',
        );
    }

    /**
     * New pending request → notify its approver (room's-faculty N2, or A3
     * for a central/shared room — Security.md §3 routing rule).
     */
    public function notifyApprover(RoomReservation $reservation): void
    {
        $approver = $this->resolveApprover($reservation);

        if ($approver === null) {
            return;
        }

        SendReservationNotification::dispatch(
            $approver->getKey(),
            __('patrimoine.reservations.notif_new_pending_title'),
            __('patrimoine.reservations.notif_new_pending_body', ['local' => $this->unscopedLocal($reservation)->code]),
            'info',
        );
    }

    /**
     * The room's-faculty N2 (single active account is enough for a
     * best-effort ping — Filament's bell is not a workflow queue), or the
     * first available A3 when the room is central/shared.
     */
    private function resolveApprover(RoomReservation $reservation): ?User
    {
        $facultyId = $this->unscopedLocal($reservation)->building->faculty_id;

        if ($facultyId !== null) {
            $n2 = User::query()
                ->where('faculty_id', $facultyId)
                ->whereHas('roles', fn (Builder $query): Builder => $query->where('name', RoleName::RESPONSABLE_FACULTE))
                ->first();

            if ($n2 !== null) {
                return $n2;
            }
        }

        return User::query()
            ->whereHas('roles', fn (Builder $query): Builder => $query->where('name', RoleName::GESTIONNAIRE_PATRIMOINE))
            ->first();
    }

    /**
     * Business logic operating on a reservation must resolve its room
     * regardless of who happens to be authenticated when it runs (e.g. a
     * Sciences-affiliated teacher's own request into a Technology room) —
     * relying on the lazily-loaded `local` relation would silently return
     * null under Local's own FacultyScope, scoped to the wrong person.
     */
    private function unscopedLocal(RoomReservation $reservation): Local
    {
        return Local::query()
            ->withoutGlobalScope(FacultyScope::class)
            ->with(['building' => fn (Relation $query): Relation => $query->withoutGlobalScope(FacultyScope::class)])
            ->findOrFail($reservation->local_id);
    }
}
