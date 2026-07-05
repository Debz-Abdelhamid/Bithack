<?php

namespace App\Filament\Resources\Locals\RelationManagers;

use App\Filament\Resources\Assignments\Tables\AssignmentsTable;
use App\Models\Assignment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only history on the room page: whole-room affectations and
 * equipment assigned into this room. Whole-room assignments are created
 * from the Assignments resource; revoke stays available here.
 */
class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('patrimoine.resources.assignment.plural');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn ($query) => $query
                    ->with(['equipment', 'service', 'assignedTo'])
                    ->latest('start_date')
            )
            ->columns([
                TextColumn::make('subject')
                    ->label(__('patrimoine.fields.assignment_subject'))
                    ->fontFamily(FontFamily::Mono)
                    ->state(
                        fn (Assignment $record): string => $record->equipment->inventory_code
                            ?? __('patrimoine.fields.whole_room')
                    )
                    ->description(fn (Assignment $record): ?string => $record->equipment?->designation),
                TextColumn::make('service.name')
                    ->label(__('patrimoine.resources.service.label'))
                    ->placeholder('—'),
                TextColumn::make('assignedTo.name')
                    ->label(__('patrimoine.fields.assigned_to'))
                    ->placeholder('—'),
                TextColumn::make('start_date')
                    ->label(__('patrimoine.fields.start_date'))
                    ->date(),
                TextColumn::make('end_date')
                    ->label(__('patrimoine.fields.end_date'))
                    ->date()
                    ->placeholder(__('patrimoine.fields.assignment_active')),
            ])
            ->recordActions([
                AssignmentsTable::revokeAction(),
            ]);
    }
}
