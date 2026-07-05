<?php

namespace App\Filament\Resources\Buildings\Pages;

use App\Filament\Pages\CampusMap;
use App\Filament\Resources\Buildings\BuildingResource;
use App\Models\Building;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateBuilding extends CreateRecord
{
    protected static string $resource = BuildingResource::class;

    /**
     * Coordinates are optional at creation — guide the admin to the campus
     * map's pick-a-location flow instead of typing latitude/longitude.
     */
    protected function afterCreate(): void
    {
        $building = $this->record;

        if ($building instanceof Building && ($building->latitude === null || $building->longitude === null)) {
            Notification::make()
                ->title(__('patrimoine.campus_map.place_reminder_title'))
                ->body(__('patrimoine.campus_map.place_reminder_body', [
                    'name' => $building->name,
                    'url' => CampusMap::getUrl(),
                ]))
                ->warning()
                ->persistent()
                ->send();
        }
    }
}
