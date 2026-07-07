<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Carbon;

/**
 * Schema.md §4 SLA rule: `+48h` urgent / `+5 business days` standard.
 * Business-day arithmetic here only skips Friday (Algeria's weekly
 * non-working day, already established in TimetableBuilder's week) — the
 * full holiday calendar is Schema.md §6 open question #1, not guessed at.
 */
enum TicketPriority: string implements HasColor, HasLabel
{
    case Urgent = 'urgent';
    case Standard = 'standard';

    public function getLabel(): string
    {
        return __('patrimoine.ticket_priorities.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Urgent => 'danger',
            self::Standard => 'warning',
        };
    }

    public function slaDueAt(Carbon $from): Carbon
    {
        if ($this === self::Urgent) {
            return $from->copy()->addHours(48);
        }

        $due = $from->copy();
        $remaining = 5;

        while ($remaining > 0) {
            $due->addDay();

            if ($due->dayOfWeek !== Carbon::FRIDAY) {
                $remaining--;
            }
        }

        return $due;
    }
}
