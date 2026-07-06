<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Schema.md §2.7 — the 2026-07-06 split: `timetable` rows are the
 * faculty-authored emploi du temps (N2/A3, created directly confirmed);
 * `request` rows are Enseignant-initiated ad-hoc bookings (starts pending).
 */
enum ReservationSource: string implements HasLabel
{
    case Timetable = 'timetable';
    case Request = 'request';

    public function getLabel(): string
    {
        return __('patrimoine.reservation_sources.'.$this->value);
    }
}
