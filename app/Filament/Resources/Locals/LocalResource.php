<?php

namespace App\Filament\Resources\Locals;

use App\Filament\Resources\Locals\Pages\CreateLocal;
use App\Filament\Resources\Locals\Pages\EditLocal;
use App\Filament\Resources\Locals\Pages\ListLocals;
use App\Filament\Resources\Locals\Schemas\LocalForm;
use App\Filament\Resources\Locals\Tables\LocalsTable;
use App\Models\Local;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LocalResource extends Resource
{
    protected static ?string $model = Local::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    public static function getNavigationGroup(): ?string
    {
        return __('patrimoine.nav.patrimoine');
    }

    public static function getModelLabel(): string
    {
        return __('patrimoine.resources.local.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('patrimoine.resources.local.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return LocalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LocalsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLocals::route('/'),
            'create' => CreateLocal::route('/create'),
            'edit' => EditLocal::route('/{record}/edit'),
        ];
    }
}
