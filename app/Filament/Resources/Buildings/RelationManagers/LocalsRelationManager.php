<?php

namespace App\Filament\Resources\Buildings\RelationManagers;

use App\Enums\LocalStatus;
use App\Enums\LocalType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LocalsRelationManager extends RelationManager
{
    protected static string $relationship = 'locals';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                    ->nullable(),
                Select::make('status')
                    ->label(__('patrimoine.fields.status'))
                    ->options(LocalStatus::class)
                    ->default(LocalStatus::Available)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('code')
                    ->label(__('patrimoine.fields.code'))
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('patrimoine.fields.name'))
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('patrimoine.fields.type'))
                    ->badge(),
                TextColumn::make('floor')
                    ->label(__('patrimoine.fields.floor')),
                TextColumn::make('capacity')
                    ->label(__('patrimoine.fields.capacity')),
                TextColumn::make('status')
                    ->label(__('patrimoine.fields.status'))
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
