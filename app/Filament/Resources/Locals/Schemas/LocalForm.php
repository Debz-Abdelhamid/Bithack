<?php

namespace App\Filament\Resources\Locals\Schemas;

use App\Enums\LocalStatus;
use App\Enums\LocalType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LocalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('building_id')
                    ->label(__('patrimoine.fields.building'))
                    ->relationship('building', 'name')
                    ->preload()
                    ->searchable()
                    ->required(),
                TextInput::make('code')
                    ->label(__('patrimoine.fields.code'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label(__('patrimoine.fields.name'))
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label(__('patrimoine.fields.type'))
                    ->options(LocalType::class)
                    ->required(),
                TextInput::make('floor')
                    ->label(__('patrimoine.fields.floor'))
                    ->numeric(),
                TextInput::make('capacity')
                    ->label(__('patrimoine.fields.capacity'))
                    ->numeric()
                    ->minValue(0),
                TextInput::make('surface_m2')
                    ->label(__('patrimoine.fields.surface_m2'))
                    ->numeric()
                    ->minValue(0),
                Select::make('responsible_user_id')
                    ->label(__('patrimoine.fields.responsible'))
                    ->relationship('responsibleUser', 'name')
                    ->preload()
                    ->searchable()
                    ->nullable()
                    ->helperText(__('patrimoine.fields.local_responsible_help')),
                Select::make('status')
                    ->label(__('patrimoine.fields.status'))
                    ->options(LocalStatus::class)
                    ->default(LocalStatus::Available)
                    ->required(),
            ]);
    }
}
