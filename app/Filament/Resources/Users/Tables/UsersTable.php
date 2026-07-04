<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('roles', 'faculty'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('patrimoine.fields.full_name'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('patrimoine.fields.email'))
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label(__('patrimoine.fields.roles'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('patrimoine.roles.'.$state)),
                TextColumn::make('faculty.name')
                    ->label(__('patrimoine.fields.faculty'))
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label(__('patrimoine.fields.is_active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('patrimoine.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label(__('patrimoine.fields.roles'))
                    ->relationship('roles', 'name'),
                SelectFilter::make('faculty')
                    ->label(__('patrimoine.fields.faculty'))
                    ->relationship('faculty', 'name'),
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
