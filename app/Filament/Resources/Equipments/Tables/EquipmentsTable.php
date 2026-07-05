<?php

namespace App\Filament\Resources\Equipments\Tables;

use App\Enums\EquipmentCondition;
use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EquipmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->with(['local.building', 'qrCode'])
            )
            ->columns([
                TextColumn::make('inventory_code')
                    ->label(__('patrimoine.fields.inventory_code'))
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('designation')
                    ->label(__('patrimoine.fields.designation'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->label(__('patrimoine.fields.category'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('local.code')
                    ->label(__('patrimoine.fields.local'))
                    ->placeholder(__('patrimoine.fields.unplaced'))
                    ->description(fn (Equipment $record): ?string => $record->local?->building?->name)
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('patrimoine.fields.status'))
                    ->badge(),
                TextColumn::make('condition')
                    ->label(__('patrimoine.fields.condition'))
                    ->badge()
                    ->toggleable(),
                IconColumn::make('qrCode.printed')
                    ->label(__('patrimoine.fields.label_printed'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('acquisition_value')
                    ->label(__('patrimoine.fields.acquisition_value'))
                    ->money('DZD')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('patrimoine.fields.status'))
                    ->options(EquipmentStatus::class),
                SelectFilter::make('condition')
                    ->label(__('patrimoine.fields.condition'))
                    ->options(EquipmentCondition::class),
                SelectFilter::make('category')
                    ->label(__('patrimoine.fields.category'))
                    ->options(
                        fn (): array => Equipment::query()
                            ->distinct()
                            ->orderBy('category')
                            ->pluck('category', 'category')
                            ->all()
                    ),
                SelectFilter::make('local')
                    ->label(__('patrimoine.fields.local'))
                    ->relationship('local', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
