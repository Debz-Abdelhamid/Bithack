<?php

namespace App\Filament\Resources\MaintenanceTickets\Pages;

use App\Filament\Resources\MaintenanceTickets\MaintenanceTicketResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * A3's manual-entry path (`source` left as whatever the form picked,
 * typically `manual`) — the automatic `qr_scan` path is
 * App\Http\Controllers\AnomalyReportController, not this page.
 */
class CreateMaintenanceTicket extends CreateRecord
{
    protected static string $resource = MaintenanceTicketResource::class;

    /**
     * The reporter is always the authenticated session, never form input.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reported_by_user_id'] = auth()->id();

        return $data;
    }
}
