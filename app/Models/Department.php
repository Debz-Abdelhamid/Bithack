<?php

namespace App\Models;

use App\Models\Scopes\FacultyScope;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Phase 5 addendum (2026-07-06) — a faculty manages several departments;
 * the faculty-authored timetable is filled per department, per academic
 * term. Faculty-scoped like Building/Local: N2 sees only their own
 * faculty's departments, A3/N3 unscoped.
 *
 * @property-read Faculty $faculty
 */
#[ScopedBy(FacultyScope::class)]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'faculty_id',
        'name',
        'code',
    ];

    /**
     * @return BelongsTo<Faculty, $this>
     */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    /**
     * @return HasMany<RoomReservation, $this>
     */
    public function roomReservations(): HasMany
    {
        return $this->hasMany(RoomReservation::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
