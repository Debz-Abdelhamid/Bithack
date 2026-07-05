<?php

namespace App\Filament\Resources\Equipments\Pages;

use App\Filament\Resources\Equipments\EquipmentResource;
use App\Models\Equipment;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewEquipment extends ViewRecord
{
    protected static string $resource = EquipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Same flow as the legacy app (ui-design.md §6): new tab → A4
            // label → auto window.print(). The link is only a shortcut —
            // authorization is enforced server-side on the label route.
            Action::make('print_label')
                ->label(__('patrimoine.qr.print_label'))
                ->icon(Heroicon::OutlinedPrinter)
                ->url(fn (Equipment $record): string => route('equipments.label', $record))
                ->openUrlInNewTab()
                ->visible(
                    fn (Equipment $record): bool => auth()->user()?->can('printLabel', $record) ?? false
                ),
            EditAction::make(),
        ];
    }
}
