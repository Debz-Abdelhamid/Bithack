<?php

namespace App\Models;

use App\Enums\ReservationLevel;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Scopes\FacultyScope;
use App\Observers\RoomReservationObserver;
use Database\Factories\RoomReservationFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Schema.md §2.7 — room_reservations. Two `source` kinds share one table
 * (2026-07-06 decision): `timetable` (faculty-authored, confirmed
 * directly) and `request` (Enseignant ad-hoc, starts pending).
 *
 * @property ReservationSource $source
 * @property ReservationLevel|null $level
 * @property ReservationStatus $status
 * @property Carbon $start_at
 * @property Carbon $end_at
 * @property-read Local $local
 * @property-read User $requestedBy
 * @property-read User|null $teacher
 * @property-read User|null $approvedBy
 */
#[ObservedBy(RoomReservationObserver::class)]
#[ScopedBy(FacultyScope::class)]
class RoomReservation extends Model
{
    /** @use HasFactory<RoomReservationFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'local_id',
        'source',
        'requested_by_user_id',
        'teacher_user_id',
        'module_name',
        'level',
        'department',
        'student_group',
        'attendees_count',
        'purpose',
        'start_at',
        'end_at',
        'recurring_rule',
        'recurring_group_id',
        'status',
        'approved_by_user_id',
        'external_calendar_ref',
    ];

    protected function casts(): array
    {
        return [
            'source' => ReservationSource::class,
            'level' => ReservationLevel::class,
            'status' => ReservationStatus::class,
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'attendees_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Local, $this>
     */
    public function local(): BelongsTo
    {
        return $this->belongsTo(Local::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOverlapping(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->where('start_at', '<', $end)->where('end_at', '>', $start);
    }

    /**
     * The DB-level guard everyone else defers to (Observer, Filament form
     * rules): does a CONFIRMED reservation already occupy this room/time?
     * Queried without global scopes — availability is a fact, not scoped
     * by who is asking.
     */
    public static function hasConfirmedOverlap(int $localId, Carbon $start, Carbon $end, ?int $excludeId = null): bool
    {
        return static::query()
            ->withoutGlobalScopes()
            ->where('local_id', $localId)
            ->where('status', ReservationStatus::Confirmed)
            ->when($excludeId !== null, fn (Builder $q): Builder => $q->whereKeyNot($excludeId))
            ->overlapping($start, $end)
            ->exists();
    }

    /**
     * Other PENDING requests competing for the same room/time — auto-
     * rejected when one of them gets confirmed (PROGRESS.md open question
     * #5 default).
     *
     * @return Collection<int, static>
     */
    public static function conflictingPending(int $localId, Carbon $start, Carbon $end, int $excludeId): Collection
    {
        return static::query()
            ->withoutGlobalScopes()
            ->where('local_id', $localId)
            ->where('status', ReservationStatus::Pending)
            ->whereKeyNot($excludeId)
            ->overlapping($start, $end)
            ->get();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
