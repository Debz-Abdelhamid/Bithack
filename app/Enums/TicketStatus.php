<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Schema.md §2.8 — also drives the Phase 7 drag-and-drop Kanban columns
 * (Phases.md Phase 7). Phase 6 only ever creates `New`; Phase 7 adds the
 * state machine below (avoid ad-hoc string checks, per Phases.md).
 */
enum TicketStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __('patrimoine.ticket_statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Assigned => 'warning',
            self::InProgress => 'primary',
            self::Resolved => 'success',
            self::Closed => 'gray',
            self::Cancelled => 'gray',
        };
    }

    /**
     * A ticket in one of these statuses still "occupies" its asset for
     * the legacy-matched duplicate-report guard (Phases.md Phase 6 DoD —
     * "cannot be trivially spammed").
     *
     * @return list<self>
     */
    public static function activeStatuses(): array
    {
        return [self::New, self::Assigned, self::InProgress];
    }

    /**
     * The Kanban board's column order (Phases.md Phase 7: "new → assigned
     * → in_progress → resolved → closed"). `Cancelled` is a side branch,
     * not a board column — it stays visible only in the plain resource
     * table, same split as `RoomReservation`'s admin table vs.
     * `TimetableBuilder`'s grid.
     *
     * @return list<self>
     */
    public static function boardColumns(): array
    {
        return [self::New, self::Assigned, self::InProgress, self::Resolved, self::Closed];
    }

    /**
     * Linear advance (matches both this column order and the legacy
     * `TicketDetailView.tsx`'s own `NEXT_STATUS` map) plus `Cancelled` as a
     * side branch reachable from any non-terminal state — no reopening
     * once `Closed`/`Cancelled` (kept minimal; not asked for a reopen flow).
     */
    public function canTransitionTo(self $target): bool
    {
        if ($target === self::Cancelled) {
            return ! in_array($this, [self::Closed, self::Cancelled], true);
        }

        return match ($this) {
            self::New => $target === self::Assigned,
            self::Assigned => $target === self::InProgress,
            self::InProgress => $target === self::Resolved,
            self::Resolved => $target === self::Closed,
            self::Closed, self::Cancelled => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Closed, self::Cancelled], true);
    }

    /**
     * The single linear "advance" target (legacy `TicketDetailView.tsx`'s
     * own "Advance Status" button), or null once terminal.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::New => self::Assigned,
            self::Assigned => self::InProgress,
            self::InProgress => self::Resolved,
            self::Resolved => self::Closed,
            self::Closed, self::Cancelled => null,
        };
    }
}
