<?php

namespace App\Filament\Resources\MaintenanceTickets;

use App\Filament\Resources\MaintenanceTickets\Pages\CreateMaintenanceTicket;
use App\Filament\Resources\MaintenanceTickets\Pages\EditMaintenanceTicket;
use App\Filament\Resources\MaintenanceTickets\Pages\ListMaintenanceTickets;
use App\Filament\Resources\MaintenanceTickets\Pages\ViewMaintenanceTicket;
use App\Filament\Resources\MaintenanceTickets\RelationManagers\InterventionsRelationManager;
use App\Filament\Resources\MaintenanceTickets\Schemas\MaintenanceTicketForm;
use App\Filament\Resources\MaintenanceTickets\Tables\MaintenanceTicketsTable;
use App\Models\MaintenanceTicket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MaintenanceTicketResource extends Resource
{
    protected static ?string $model = MaintenanceTicket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    public static function getNavigationGroup(): ?string
    {
        return __('patrimoine.nav.patrimoine');
    }

    public static function getModelLabel(): string
    {
        return __('patrimoine.resources.maintenance_ticket.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('patrimoine.resources.maintenance_ticket.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return MaintenanceTicketForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaintenanceTicketsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            InterventionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceTickets::route('/'),
            'create' => CreateMaintenanceTicket::route('/create'),
            'view' => ViewMaintenanceTicket::route('/{record}'),
            'edit' => EditMaintenanceTicket::route('/{record}/edit'),
        ];
    }
}
