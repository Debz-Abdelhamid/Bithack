<?php

namespace App\Models\Scopes;

use App\Models\Building;
use App\Models\Local;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Security.md §3 — faculty-bound users (N2) only see their own faculty's
 * patrimoine data, plus central/shared records (faculty NULL). Users without
 * a faculty (A3, N3, admin, service technique) are never restricted, and the
 * ungranted `ViewAcrossFaculties` permission is the data-driven escape hatch
 * for any future faculty-affiliated-but-global user.
 *
 * Deliberate bypasses (documented in Phases.md): the campus map page and the
 * Phase 5 room catalog query withoutGlobalScope(FacultyScope::class) — the
 * physical campus is public and teachers book campus-wide; this scope guards
 * the administrative resource surface, not discovery.
 */
class FacultyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (! $user instanceof User || $user->faculty_id === null) {
            return;
        }

        if ($user->can('ViewAcrossFaculties')) {
            return;
        }

        if ($model instanceof Building) {
            $builder->where(function (Builder $query) use ($model, $user): void {
                $query->where($model->qualifyColumn('faculty_id'), $user->faculty_id)
                    ->orWhereNull($model->qualifyColumn('faculty_id'));
            });

            return;
        }

        if ($model instanceof Local) {
            $builder->whereHas('building', function (Builder $query) use ($user): void {
                $query->where(function (Builder $inner) use ($user): void {
                    $inner->where('faculty_id', $user->faculty_id)
                        ->orWhereNull('faculty_id');
                });
            });
        }
    }
}
