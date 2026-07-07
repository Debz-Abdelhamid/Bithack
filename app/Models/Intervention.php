<?php

namespace App\Models;

use App\Enums\InterventionStatus;
use App\Observers\InterventionObserver;
use Database\Factories\InterventionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Schema.md §2.9 — planned/carried-out work against a ticket.
 *
 * @property InterventionStatus $status
 * @property-read MaintenanceTicket $maintenanceTicket
 * @property-read User|null $technician
 */
#[ObservedBy(InterventionObserver::class)]
class Intervention extends Model
{
    /** @use HasFactory<InterventionFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'maintenance_ticket_id',
        'technician_id',
        'scheduled_at',
        'completed_at',
        'report',
        'cost',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => InterventionStatus::class,
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'cost' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<MaintenanceTicket, $this>
     */
    public function maintenanceTicket(): BelongsTo
    {
        return $this->belongsTo(MaintenanceTicket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
