<?php

namespace App\Models;

use App\Models\Scopes\FacultyScope;
use App\Observers\AssignmentObserver;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Affectation (Schema.md §2.6): an equipment, a whole room, or an
 * equipment moving into a room, assigned to a service and/or a person.
 * Active = end_date NULL; the observer keeps at most one active
 * assignment per subject and preserves the closed ones as history.
 *
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property-read Equipment|null $equipment
 * @property-read Local|null $local
 * @property-read Service|null $service
 * @property-read User|null $assignedTo
 * @property-read User $assignedBy
 */
#[ObservedBy(AssignmentObserver::class)]
#[ScopedBy(FacultyScope::class)]
class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'equipment_id',
        'local_id',
        'service_id',
        'assigned_to_user_id',
        'assigned_by_user_id',
        'start_date',
        'end_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('end_date');
    }

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * @return BelongsTo<Local, $this>
     */
    public function local(): BelongsTo
    {
        return $this->belongsTo(Local::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function isActive(): bool
    {
        return $this->end_date === null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
