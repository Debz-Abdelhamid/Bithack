<?php

namespace App\Filament\Resources\MaintenanceTickets\RelationManagers;

use App\Enums\InterventionStatus;
use App\Models\Intervention;
use App\Support\RoleName;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Schema.md §2.9 — A3 assigns a technician/schedule; the assigned
 * technician (Service technique) later logs their own report/cost/
 * completion (`InterventionPolicy::logWork()`).
 */
class InterventionsRelationManager extends RelationManager
{
    protected static string $relationship = 'interventions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('patrimoine.resources.intervention.plural');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('technician_id')
                ->label(__('patrimoine.fields.technician'))
                ->relationship(
                    'technician',
                    'name',
                    fn (Builder $query): Builder => $query->whereHas(
                        'roles',
                        fn (Builder $q): Builder => $q->where('name', RoleName::SERVICE_TECHNIQUE)
                    ),
                )
                ->searchable()
                ->nullable(),
            DateTimePicker::make('scheduled_at')
                ->label(__('patrimoine.fields.scheduled_at')),
            DateTimePicker::make('completed_at')
                ->label(__('patrimoine.fields.completed_at')),
            TextInput::make('cost')
                ->label(__('patrimoine.fields.cost'))
                ->numeric()
                ->minValue(0)
                ->suffix('DZD'),
            Select::make('status')
                ->label(__('patrimoine.fields.status'))
                ->options(InterventionStatus::class)
                ->default(InterventionStatus::Planned)
                ->required(),
            Textarea::make('report')
                ->label(__('patrimoine.fields.report'))
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('technician.name')
                    ->label(__('patrimoine.fields.technician'))
                    ->placeholder(__('patrimoine.fields.unassigned')),
                TextColumn::make('scheduled_at')
                    ->label(__('patrimoine.fields.scheduled_at'))
                    ->dateTime(),
                TextColumn::make('status')
                    ->label(__('patrimoine.fields.status'))
                    ->badge(),
                TextColumn::make('cost')
                    ->label(__('patrimoine.fields.cost'))
                    ->money('DZD'),
                TextColumn::make('completed_at')
                    ->label(__('patrimoine.fields.completed_at'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(
                        fn (Intervention $record): bool => (auth()->user()?->can('update', $record) ?? false)
                            || (auth()->user()?->can('logWork', $record) ?? false)
                    ),
            ]);
    }
}
