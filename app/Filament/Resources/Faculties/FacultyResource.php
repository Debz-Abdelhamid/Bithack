<?php

namespace App\Filament\Resources\Faculties;

use App\Filament\Resources\Faculties\Pages\ManageFaculties;
use App\Models\Faculty;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FacultyResource extends Resource
{
    protected static ?string $model = Faculty::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    public static function getNavigationGroup(): ?string
    {
        return __('patrimoine.nav.referentiel');
    }

    public static function getModelLabel(): string
    {
        return __('patrimoine.resources.faculty.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('patrimoine.resources.faculty.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label(__('patrimoine.fields.code'))
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label(__('patrimoine.fields.name'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('patrimoine.fields.code'))
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('patrimoine.fields.name'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFaculties::route('/'),
        ];
    }
}
