<?php

namespace App\Filament\Resources\MaintenanceTickets\Pages;

use App\Filament\Resources\MaintenanceTickets\MaintenanceTicketResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceTickets extends ListRecords
{
    protected static string $resource = MaintenanceTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
