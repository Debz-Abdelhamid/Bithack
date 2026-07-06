<?php

namespace App\Models;

use Database\Factories\AcademicTermFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Phase 5 addendum (2026-07-06) — the academic year splits into 2
 * semesters; a faculty-authored timetable slot belongs to one term, and
 * its weekly recurrence runs to the term's end_date (the authoritative
 * boundary, replacing the previous arbitrary "repeat until" date).
 * University-wide referential (like Faculty) — not faculty-scoped.
 *
 * @property string $academic_year
 * @property int $semester
 * @property Carbon $start_date
 * @property Carbon $end_date
 */
class AcademicTerm extends Model
{
    /** @use HasFactory<AcademicTermFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'academic_year',
        'semester',
        'label',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'semester' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AcademicTerm $term): void {
            if (blank($term->label)) {
                $term->label = "{$term->academic_year} — Semester {$term->semester}";
            }
        });
    }

    /**
     * @return HasMany<RoomReservation, $this>
     */
    public function roomReservations(): HasMany
    {
        return $this->hasMany(RoomReservation::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
