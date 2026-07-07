<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Models\Scopes\FacultyScope;
use App\Observers\MaintenanceTicketObserver;
use Database\Factories\MaintenanceTicketFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Schema.md §2.8. Created automatically from a QR scan (Phase 6,
 * `source = qr_scan`) or entered directly by A3 (`manual`).
 *
 * @property string $reference
 * @property TicketSource $source
 * @property TicketPriority $priority
 * @property TicketStatus $status
 * @property Carbon $sla_due_at
 * @property Carbon|null $escalated_at
 * @property-read Equipment|null $equipment
 * @property-read Local|null $local
 * @property-read User $reportedBy
 * @property-read Service|null $assignedService
 */
#[ObservedBy(MaintenanceTicketObserver::class)]
#[ScopedBy(FacultyScope::class)]
class MaintenanceTicket extends Model
{
    /** @use HasFactory<MaintenanceTicketFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'reference',
        'equipment_id',
        'local_id',
        'reported_by_user_id',
        'source',
        'description',
        'priority',
        'sla_due_at',
        'category',
        'status',
        'assigned_service_id',
        'escalated_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => TicketSource::class,
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'sla_due_at' => 'datetime',
            'escalated_at' => 'datetime',
        ];
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
     * @return BelongsTo<User, $this>
     */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function assignedService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'assigned_service_id');
    }

    /**
     * @return HasMany<Intervention, $this>
     */
    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }

    /**
     * Legacy-matched duplicate-report guard (Phases.md Phase 6 DoD): does
     * this equipment already have a ticket that hasn't reached a terminal
     * status? Queried without global scopes — "is this asset already
     * being handled" is a fact, not scoped by who is asking (mirrors
     * RoomReservation::hasConfirmedOverlap's precedent).
     */
    public static function hasActiveTicketFor(int $equipmentId): bool
    {
        return static::query()
            ->withoutGlobalScopes()
            ->where('equipment_id', $equipmentId)
            ->whereIn('status', TicketStatus::activeStatuses())
            ->exists();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', TicketStatus::activeStatuses());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
