<?php

namespace App\Observers;

use App\Enums\ReservationStatus;
use App\Exceptions\OverlappingReservationException;
use App\Models\RoomReservation;

/**
 * Phase 5 DoD — "two users cannot double-book the same room/time",
 * enforced regardless of how the row is being saved (Filament form,
 * confirm action, factory, tinker). Filament form rules give a friendly
 * field error earlier in the normal path; this is the driver-agnostic
 * guard underneath. Extended 2026-07-09 with a second conflict axis: the
 * same named student group can't be in two confirmed classes at once
 * either, even across two different rooms (RoomReservation::
 * hasConfirmedGroupOverlap doc block has the full reasoning).
 */
class RoomReservationObserver
{
    public function saving(RoomReservation $reservation): void
    {
        if ($reservation->status !== ReservationStatus::Confirmed) {
            return;
        }

        $excludeId = $reservation->exists ? $reservation->getKey() : null;

        $overlapsRoom = RoomReservation::hasConfirmedOverlap(
            $reservation->local_id,
            $reservation->start_at,
            $reservation->end_at,
            $excludeId,
        );

        $overlapsGroup = $reservation->department_id !== null && RoomReservation::hasConfirmedGroupOverlap(
            $reservation->department_id,
            $reservation->level,
            $reservation->student_group,
            $reservation->start_at,
            $reservation->end_at,
            $excludeId,
        );

        if ($overlapsRoom || $overlapsGroup) {
            throw new OverlappingReservationException;
        }
    }
}
