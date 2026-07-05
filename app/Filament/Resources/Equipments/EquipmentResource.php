<?php

namespace App\Filament\Resources\Equipments;

use App\Filament\Resources\Equipments\Pages\CreateEquipment;
use App\Filament\Resources\Equipments\Pages\EditEquipment;
use App\Filament\Resources\Equipments\Pages\ListEquipments;
use App\Filament\Resources\Equipments\Pages\ViewEquipment;
use App\Filament\Resources\Equipments\Schemas\EquipmentForm;
use App\Filament\Resources\Equipments\Schemas\EquipmentInfolist;
use App\Filament\Resources\Equipments\Tables\EquipmentsTable;
use App\Models\Equipment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EquipmentResource extends Resource
{
    protected static ?string $model = Equipment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    public static function getNavigationGroup(): ?string
    {
        return __('patrimoine.nav.patrimoine');
    }

    public static function getModelLabel(): string
    {
        return __('patrimoine.resources.equipment.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('patrimoine.resources.equipment.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return EquipmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EquipmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EquipmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEquipments::route('/'),
            'create' => CreateEquipment::route('/create'),
            'view' => ViewEquipment::route('/{record}'),
            'edit' => EditEquipment::route('/{record}/edit'),
        ];
    }
}
