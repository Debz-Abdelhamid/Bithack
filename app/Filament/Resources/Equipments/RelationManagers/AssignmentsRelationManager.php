<?php

namespace App\Filament\Resources\Equipments\RelationManagers;

use App\Filament\Resources\Assignments\Schemas\AssignmentForm;
use App\Filament\Resources\Assignments\Tables\AssignmentsTable;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * ui-design.md §5 — the asset detail page shows the current affectation
 * (+ revoke) and the full history of who had it, when (Phase 4 DoD).
 * Creating from here is the old app's "Affect" modal: the equipment is
 * bound automatically, the room select is the optional destination.
 */
class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('patrimoine.resources.assignment.plural');
    }

    public function form(Schema $schema): Schema
    {
        return AssignmentForm::configure($schema, withSubject: false);
    }

    public function table(Table $table): Table
    {
        return AssignmentsTable::configure($table, withSubject: false)
            ->headerActions([
                CreateAction::make()
                    ->label(__('patrimoine.fields.assign'))
                    ->mutateDataUsing(function (array $data): array {
                        $data['assigned_by_user_id'] = auth()->id();

                        return $data;
                    }),
            ]);
    }
}
