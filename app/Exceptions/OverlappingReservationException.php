<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by RoomReservationObserver whenever a save would leave two
 * CONFIRMED reservations overlapping on the same room. Filament form
 * rules catch this case earlier with a friendly field error; this
 * exception is the hard, driver-agnostic guard underneath (Postgres also
 * gets a real EXCLUDE constraint — see the room_reservations migration).
 */
class OverlappingReservationException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('patrimoine.reservations.overlap_error'));
    }
}
