<?php

namespace App\Filament\Resources\RoomReservations\Pages;

use App\Enums\ReservationStatus;
use App\Filament\Resources\RoomReservations\RoomReservationResource;
use App\Models\RoomReservation;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditRoomReservation extends EditRecord
{
    protected static string $resource = RoomReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Timetable rows are never hard-deleted (history matters, same
            // principle as Assignments) — cancelling is the reversible
            // equivalent, mirroring Assignment's "Revoke" action.
            Action::make('cancel')
                ->label(__('patrimoine.fields.cancel_reservation'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->requiresConfirmation()
                ->visible(
                    fn (RoomReservation $record): bool => $record->status !== ReservationStatus::Cancelled
                        && (auth()->user()?->can('cancel', $record) ?? false)
                )
                ->action(function (RoomReservation $record): void {
                    $record->update(['status' => ReservationStatus::Cancelled]);
                    $this->redirect(static::getResource()::getUrl('index'));
                }),
        ];
    }
}
