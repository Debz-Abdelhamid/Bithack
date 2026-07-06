<?php

namespace App\Filament\Resources\RoomReservations\Pages;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Filament\Resources\RoomReservations\RoomReservationResource;
use App\Models\AcademicTerm;
use App\Services\TimetableSlotService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * Always creates `timetable` rows (Security.md §3, Phases.md Phase 5):
 * the requester is whoever is entering it (N2/A3), created directly
 * `confirmed` — authorship by an authorized role IS the approval.
 *
 * "Repeat weekly" expands into one row per week through the selected
 * Academic Term's end date (Phase 5 addendum, 2026-07-06) — delegated to
 * TimetableSlotService, shared with the visual timetable grid page.
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
        unset($data['repeat_weekly']);

        $term = AcademicTerm::query()->findOrFail($data['academic_term_id']);
        unset($data['academic_term_id']);

        $result = app(TimetableSlotService::class)->createSeries($data, $term, $repeatWeekly);

        if ($result['created'] === null) {
            Notification::make()
                ->danger()
                ->title(__('patrimoine.reservations.series_conflict', ['date' => $result['conflictDate']]))
                ->send();

            throw new Halt;
        }

        return $result['created'];
    }
}
