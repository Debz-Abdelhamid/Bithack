<?php

namespace App\Filament\Resources\MaintenanceTickets\Tables;

use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Models\MaintenanceTicket;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaintenanceTicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->with(['equipment', 'local.building', 'assignedService'])
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->label(__('patrimoine.fields.reference'))
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('equipment.designation')
                    ->label(__('patrimoine.fields.equipment'))
                    ->placeholder(__('patrimoine.fields.no_equipment'))
                    ->description(function (MaintenanceTicket $record): ?string {
                        $local = $record->local ?? $record->equipment?->local;

                        return $local?->code;
                    })
                    ->searchable(),
                TextColumn::make('category')
                    ->label(__('patrimoine.fields.category'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('priority')
                    ->label(__('patrimoine.fields.priority'))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('patrimoine.fields.status'))
                    ->badge(),
                TextColumn::make('sla_due_at')
                    ->label(__('patrimoine.fields.sla_due_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('source')
                    ->label(__('patrimoine.fields.source'))
                    ->badge()
                    ->toggleable(),
                TextColumn::make('assignedService.name')
                    ->label(__('patrimoine.fields.assigned_service'))
                    ->placeholder(__('patrimoine.fields.unassigned'))
                    ->toggleable(),
                TextColumn::make('reportedBy.name')
                    ->label(__('patrimoine.fields.reported_by'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('patrimoine.fields.status'))
                    ->options(TicketStatus::class),
                SelectFilter::make('priority')
                    ->label(__('patrimoine.fields.priority'))
                    ->options(TicketPriority::class),
                SelectFilter::make('source')
                    ->label(__('patrimoine.fields.source'))
                    ->options(TicketSource::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
