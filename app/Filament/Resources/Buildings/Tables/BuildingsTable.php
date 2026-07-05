<?php

namespace App\Filament\Resources\Buildings\Tables;

use App\Enums\BuildingStatus;
use App\Models\Building;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BuildingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->with('faculty')->withCount('locals')
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
                TextColumn::make('faculty.name')
                    ->label(__('patrimoine.fields.faculty'))
                    ->placeholder(__('patrimoine.fields.central_shared'))
                    ->sortable(),
                TextColumn::make('campus')
                    ->label(__('patrimoine.fields.campus'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('locals_count')
                    ->label(__('patrimoine.fields.locals_count'))
                    ->badge()
                    ->sortable(),
                IconColumn::make('has_coordinates')
                    ->label(__('patrimoine.fields.on_map'))
                    ->state(fn (Building $record): bool => $record->latitude !== null && $record->longitude !== null)
                    ->boolean(),
                TextColumn::make('status')
                    ->label(__('patrimoine.fields.status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('faculty')
                    ->label(__('patrimoine.fields.faculty'))
                    ->relationship('faculty', 'name'),
                SelectFilter::make('status')
                    ->label(__('patrimoine.fields.status'))
                    ->options(BuildingStatus::class),
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
