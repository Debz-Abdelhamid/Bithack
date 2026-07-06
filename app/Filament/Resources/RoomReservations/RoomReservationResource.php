<?php

namespace App\Filament\Resources\RoomReservations;

use App\Filament\Resources\RoomReservations\Pages\CreateRoomReservation;
use App\Filament\Resources\RoomReservations\Pages\EditRoomReservation;
use App\Filament\Resources\RoomReservations\Pages\ListRoomReservations;
use App\Filament\Resources\RoomReservations\Schemas\RoomReservationForm;
use App\Filament\Resources\RoomReservations\Tables\RoomReservationsTable;
use App\Models\RoomReservation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Admin surface for N2 (own faculty)/A3 (everywhere): enter/edit the
 * timetable directly, and confirm/reject pending Enseignant requests.
 * Enseignant never sees this resource (no ViewAny/View) — they submit
 * and cancel their own requests from a separate page instead.
 */
class RoomReservationResource extends Resource
{
    protected static ?string $model = RoomReservation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public static function getNavigationGroup(): ?string
    {
        return __('patrimoine.nav.patrimoine');
    }

    public static function getModelLabel(): string
    {
        return __('patrimoine.resources.room_reservation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('patrimoine.resources.room_reservation.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return RoomReservationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoomReservationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoomReservations::route('/'),
            'create' => CreateRoomReservation::route('/create'),
            'edit' => EditRoomReservation::route('/{record}/edit'),
        ];
    }
}
