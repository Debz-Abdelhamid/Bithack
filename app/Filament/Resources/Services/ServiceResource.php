<?php

namespace App\Filament\Resources\Services;

use App\Enums\ServiceType;
use App\Filament\Resources\Services\Pages\ManageServices;
use App\Models\Service;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    public static function getNavigationGroup(): ?string
    {
        return __('patrimoine.nav.referentiel');
    }

    public static function getModelLabel(): string
    {
        return __('patrimoine.resources.service.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('patrimoine.resources.service.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('faculty_id')
                    ->label(__('patrimoine.fields.faculty'))
                    ->relationship('faculty', 'name')
                    ->preload()
                    ->searchable()
                    ->nullable(),
                TextInput::make('name')
                    ->label(__('patrimoine.fields.name'))
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label(__('patrimoine.fields.type'))
                    ->options(ServiceType::class)
                    ->required(),
                Select::make('responsible_user_id')
                    ->label(__('patrimoine.fields.responsible'))
                    ->relationship('responsibleUser', 'name')
                    ->preload()
                    ->searchable()
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('faculty', 'responsibleUser'))
            ->columns([
                TextColumn::make('faculty.name')
                    ->label(__('patrimoine.fields.faculty'))
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('patrimoine.fields.name'))
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('patrimoine.fields.type'))
                    ->badge(),
                TextColumn::make('responsibleUser.name')
                    ->label(__('patrimoine.fields.responsible'))
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
            'index' => ManageServices::route('/'),
        ];
    }
}
