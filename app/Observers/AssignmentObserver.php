<?php

namespace App\Observers;

use App\Models\Assignment;
use Illuminate\Database\Eloquent\Builder;

/**
 * Étape 2 (Phases.md Phase 4 DoD): assigning an asset updates its current
 * location/service and preserves full history. A new active assignment
 * closes the previous active one on the same subject (never deletes it),
 * and an equipment assignment that carries a destination room moves the
 * equipment there.
 */
class AssignmentObserver
{
    public function created(Assignment $assignment): void
    {
        if ($assignment->end_date !== null) {
            return; // Backfilled history rows close nothing.
        }

        $this->closePreviousActive($assignment);
        $this->syncEquipmentLocation($assignment);
    }

    private function closePreviousActive(Assignment $assignment): void
    {
        $previous = Assignment::query()
            ->withoutGlobalScopes()
            ->whereKeyNot($assignment->getKey())
            ->whereNull('end_date')
            ->where(function (Builder $query) use ($assignment): void {
                if ($assignment->equipment_id !== null) {
                    // Same equipment, whatever room it was tied to.
                    $query->where('equipment_id', $assignment->equipment_id);

                    return;
                }

                // Whole-room assignment: only closes other whole-room
                // assignments of that room, not equipment sitting in it.
                $query->where('local_id', $assignment->local_id)
                    ->whereNull('equipment_id');
            })
            ->get();

        // Saved one by one so the closure lands in the activity log.
        foreach ($previous as $old) {
            $old->update(['end_date' => $assignment->start_date]);
        }
    }

    private function syncEquipmentLocation(Assignment $assignment): void
    {
        if ($assignment->equipment_id === null || $assignment->local_id === null) {
            return;
        }

        $equipment = $assignment->equipment()->withoutGlobalScopes()->first();

        if ($equipment !== null && $equipment->local_id !== $assignment->local_id) {
            $equipment->update(['local_id' => $assignment->local_id]);
        }
    }
}
