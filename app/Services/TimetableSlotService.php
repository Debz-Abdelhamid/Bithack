<?php

namespace App\Services;

use App\Enums\ReservationLevel;
use App\Models\AcademicTerm;
use App\Models\RoomReservation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Phase 5 addendum (2026-07-06): shared by the plain admin form
 * (RoomReservationResource) and the visual timetable grid — a single
 * source of truth for "generate a weekly-recurring timetable slot,
 * bounded by its academic term's end date". Whole series is pre-validated
 * for conflicts before anything is persisted (all-or-nothing), mirroring
 * the pattern already used for the arbitrary-date recurrence it replaces.
 */
class TimetableSlotService
{
    /**
     * @param  array<string, mixed>  $data  Base RoomReservation attributes
     *                                      (must include local_id, start_at, end_at for
     *                                      the first occurrence; academic_term_id is set
     *                                      here from $term, so omit it from $data).
     * @return array{created: ?RoomReservation, conflictDate: ?string}
     */
    public function createSeries(array $data, AcademicTerm $term, bool $repeatWeekly): array
    {
        $data['academic_term_id'] = $term->id;

        if (! $repeatWeekly) {
            if ($this->hasConflict($data, Carbon::parse($data['start_at']), Carbon::parse($data['end_at']))) {
                return ['created' => null, 'conflictDate' => Carbon::parse($data['start_at'])->toDateString()];
            }

            return ['created' => RoomReservation::create($data), 'conflictDate' => null];
        }

        $occurrences = $this->generateWeeklyOccurrences(
            Carbon::parse($data['start_at']),
            Carbon::parse($data['end_at']),
            $term->end_date->copy()->endOfDay(),
        );

        foreach ($occurrences as [$occurrenceStart, $occurrenceEnd]) {
            if ($this->hasConflict($data, $occurrenceStart, $occurrenceEnd)) {
                return ['created' => null, 'conflictDate' => $occurrenceStart->toDateString()];
            }
        }

        $groupId = (string) Str::uuid();
        $recurringRule = 'WEEKLY;UNTIL='.$term->end_date->toDateString();
        $first = null;

        foreach ($occurrences as [$occurrenceStart, $occurrenceEnd]) {
            $record = RoomReservation::create([
                ...$data,
                'start_at' => $occurrenceStart,
                'end_at' => $occurrenceEnd,
                'recurring_group_id' => $groupId,
                'recurring_rule' => $recurringRule,
            ]);

            $first ??= $record;
        }

        return ['created' => $first, 'conflictDate' => null];
    }

    /**
     * Two independent conflict axes (owner-clarified 2026-07-09, see
     * RoomReservation::hasConfirmedGroupOverlap): the room can't be double-
     * booked, and separately the same named student group can't be double-
     * booked either — a parallel class for a DIFFERENT room and/or
     * DIFFERENT group at the same day/time is not a conflict at all.
     *
     * @param  array<string, mixed>  $data
     */
    private function hasConflict(array $data, Carbon $start, Carbon $end): bool
    {
        if (RoomReservation::hasConfirmedOverlap((int) $data['local_id'], $start, $end)) {
            return true;
        }

        if (! isset($data['department_id'])) {
            return false;
        }

        $level = $data['level'] ?? null;

        return RoomReservation::hasConfirmedGroupOverlap(
            (int) $data['department_id'],
            $level instanceof ReservationLevel ? $level : ReservationLevel::tryFrom((string) $level),
            $data['student_group'] ?? null,
            $start,
            $end,
        );
    }

    /**
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    private function generateWeeklyOccurrences(Carbon $start, Carbon $end, Carbon $until): array
    {
        $occurrences = [];
        $cursorStart = $start->copy();
        $cursorEnd = $end->copy();

        while ($cursorStart->lte($until)) {
            $occurrences[] = [$cursorStart->copy(), $cursorEnd->copy()];
            $cursorStart->addWeek();
            $cursorEnd->addWeek();
        }

        return $occurrences;
    }
}
