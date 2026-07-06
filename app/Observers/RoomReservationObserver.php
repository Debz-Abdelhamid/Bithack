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
 * guard underneath.
 */
class RoomReservationObserver
{
    public function saving(RoomReservation $reservation): void
    {
        if ($reservation->status !== ReservationStatus::Confirmed) {
            return;
        }

        $overlaps = RoomReservation::hasConfirmedOverlap(
            $reservation->local_id,
            $reservation->start_at,
            $reservation->end_at,
            $reservation->exists ? $reservation->getKey() : null,
        );

        if ($overlaps) {
            throw new OverlappingReservationException;
        }
    }
}
