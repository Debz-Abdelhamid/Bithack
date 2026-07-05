<?php

namespace App\Filament\Resources\Buildings\Schemas;

use App\Enums\BuildingStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BuildingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('faculty_id')
                    ->label(__('patrimoine.fields.faculty'))
                    ->relationship('faculty', 'name')
                    ->preload()
                    ->searchable()
                    ->nullable()
                    ->helperText(__('patrimoine.fields.building_faculty_help')),
                TextInput::make('code')
                    ->label(__('patrimoine.fields.code'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label(__('patrimoine.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('campus')
                    ->label(__('patrimoine.fields.campus'))
                    ->maxLength(255),
                TextInput::make('address')
                    ->label(__('patrimoine.fields.address'))
                    ->maxLength(255),
                TextInput::make('floors_count')
                    ->label(__('patrimoine.fields.floors_count'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(200),
                Select::make('status')
                    ->label(__('patrimoine.fields.status'))
                    ->options(BuildingStatus::class)
                    ->default(BuildingStatus::Active)
                    ->required(),
                TextInput::make('latitude')
                    ->label(__('patrimoine.fields.latitude'))
                    ->numeric()
                    ->minValue(-90)
                    ->maxValue(90)
                    ->helperText(__('patrimoine.fields.coordinates_help')),
                TextInput::make('longitude')
                    ->label(__('patrimoine.fields.longitude'))
                    ->numeric()
                    ->minValue(-180)
                    ->maxValue(180),
            ]);
    }
}
