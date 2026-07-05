<?php

namespace App\Filament\Resources\Assignments\Tables;

use App\Models\Assignment;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssignmentsTable
{
    /**
     * $withSubject=false is used by the relation managers, where the
     * subject is the page's own record.
     */
    public static function configure(Table $table, bool $withSubject = true): Table
    {
        $columns = [];

        if ($withSubject) {
            $columns[] = TextColumn::make('subject')
                ->label(__('patrimoine.fields.assignment_subject'))
                ->fontFamily(FontFamily::Mono)
                ->state(
                    fn (Assignment $record): string => $record->equipment !== null
                        ? $record->equipment->inventory_code
                        : "{$record->local?->code}"
                )
                ->description(
                    fn (Assignment $record): ?string => $record->equipment->designation
                        ?? $record->local?->name
                );
        }

        $columns = [
            ...$columns,
            TextColumn::make('local.code')
                ->label(__('patrimoine.fields.local'))
                ->placeholder('—')
                ->toggleable(),
            TextColumn::make('service.name')
                ->label(__('patrimoine.resources.service.label'))
                ->placeholder('—')
                ->sortable(),
            TextColumn::make('assignedTo.name')
                ->label(__('patrimoine.fields.assigned_to'))
                ->placeholder('—'),
            TextColumn::make('start_date')
                ->label(__('patrimoine.fields.start_date'))
                ->date()
                ->sortable(),
            TextColumn::make('end_date')
                ->label(__('patrimoine.fields.end_date'))
                ->date()
                ->placeholder(__('patrimoine.fields.assignment_active'))
                ->sortable(),
            IconColumn::make('is_active')
                ->label(__('patrimoine.fields.assignment_active'))
                ->state(fn (Assignment $record): bool => $record->isActive())
                ->boolean(),
            TextColumn::make('assignedBy.name')
                ->label(__('patrimoine.fields.assigned_by'))
                ->toggleable(isToggledHiddenByDefault: true),
        ];

        return $table
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query
                    ->with(['equipment', 'local', 'service', 'assignedTo', 'assignedBy'])
                    ->latest('start_date')
            )
            ->columns($columns)
            ->filters([
                TernaryFilter::make('active')
                    ->label(__('patrimoine.fields.assignment_active'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNull('end_date'),
                        false: fn (Builder $query): Builder => $query->whereNotNull('end_date'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                SelectFilter::make('service')
                    ->label(__('patrimoine.resources.service.label'))
                    ->relationship('service', 'name'),
            ])
            ->recordActions([
                self::revokeAction(),
                EditAction::make(),
            ]);
    }

    /**
     * Revoke = end the affectation today. History is preserved — the row
     * is closed, never deleted (Phase 4 DoD). Policy-checked via the
     * update ability, logged by the model's LogsActivity.
     */
    public static function revokeAction(): Action
    {
        return Action::make('revoke')
            ->label(__('patrimoine.fields.revoke'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(
                fn (Assignment $record): bool => $record->isActive()
                    && (auth()->user()?->can('update', $record) ?? false)
            )
            ->action(function (Assignment $record): void {
                $record->update(['end_date' => today()]);
            });
    }
}
