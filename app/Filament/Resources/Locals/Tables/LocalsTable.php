<?php

namespace App\Filament\Resources\Locals\Tables;

use App\Enums\LocalStatus;
use App\Enums\LocalType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LocalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->with(['building', 'responsibleUser'])
            )
            ->columns([
                TextColumn::make('code')
                    ->label(__('patrimoine.fields.code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('patrimoine.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('building.name')
                    ->label(__('patrimoine.fields.building'))
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('patrimoine.fields.type'))
                    ->badge(),
                TextColumn::make('floor')
                    ->label(__('patrimoine.fields.floor'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('capacity')
                    ->label(__('patrimoine.fields.capacity'))
                    ->sortable(),
                TextColumn::make('responsibleUser.name')
                    ->label(__('patrimoine.fields.responsible'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label(__('patrimoine.fields.status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('building')
                    ->label(__('patrimoine.fields.building'))
                    ->relationship('building', 'name'),
                SelectFilter::make('type')
                    ->label(__('patrimoine.fields.type'))
                    ->options(LocalType::class),
                SelectFilter::make('status')
                    ->label(__('patrimoine.fields.status'))
                    ->options(LocalStatus::class),
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
