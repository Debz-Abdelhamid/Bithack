<?php

namespace App\Filament\Resources\RoomReservations\Pages;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Filament\Resources\RoomReservations\RoomReservationResource;
use App\Models\RoomReservation;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Always creates `timetable` rows (Security.md §3, Phases.md Phase 5):
 * the requester is whoever is entering it (N2/A3), created directly
 * `confirmed` — authorship by an authorized role IS the approval.
 *
 * "Repeat weekly" expands into one row per week up to the picked end
 * date, sharing a `recurring_group_id`. Every occurrence is checked for a
 * confirmed overlap BEFORE anything is persisted — a conflict anywhere in
 * the series aborts the whole series (Halt), never a partial save.
 */
class CreateRoomReservation extends CreateRecord
{
    protected static string $resource = RoomReservationResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['source'] = ReservationSource::Timetable;
        $data['status'] = ReservationStatus::Confirmed;
        $data['requested_by_user_id'] = auth()->id();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $repeatWeekly = (bool) ($data['repeat_weekly'] ?? false);
        $repeatUntil = $data['repeat_until'] ?? null;
        unset($data['repeat_weekly'], $data['repeat_until']);

        if (! $repeatWeekly || blank($repeatUntil)) {
            return RoomReservation::create($data);
        }

        $occurrences = $this->generateWeeklyOccurrences(
            Carbon::parse($data['start_at']),
            Carbon::parse($data['end_at']),
            Carbon::parse($repeatUntil)->endOfDay(),
        );

        foreach ($occurrences as [$occurrenceStart, $occurrenceEnd]) {
            if (RoomReservation::hasConfirmedOverlap((int) $data['local_id'], $occurrenceStart, $occurrenceEnd)) {
                Notification::make()
                    ->danger()
                    ->title(__('patrimoine.reservations.series_conflict', ['date' => $occurrenceStart->toDateString()]))
                    ->send();

                throw new Halt;
            }
        }

        $groupId = (string) Str::uuid();
        $recurringRule = 'WEEKLY;UNTIL='.Carbon::parse($repeatUntil)->toDateString();
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

        return $first;
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
