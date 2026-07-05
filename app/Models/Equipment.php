<?php

namespace App\Models;

use App\Enums\EquipmentCondition;
use App\Enums\EquipmentStatus;
use App\Models\Scopes\FacultyScope;
use App\Observers\EquipmentObserver;
use Database\Factories\EquipmentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $inventory_code
 * @property EquipmentStatus $status
 * @property EquipmentCondition $condition
 * @property-read Local|null $local
 * @property-read PurchaseReference|null $purchaseReference
 * @property-read QrCode|null $qrCode
 */
#[ObservedBy(EquipmentObserver::class)]
#[ScopedBy(FacultyScope::class)]
class Equipment extends Model
{
    /** @use HasFactory<EquipmentFactory> */
    use HasFactory;

    use LogsActivity;

    // Laravel's inflector treats "equipment" as uncountable — pin the
    // Schema.md table name explicitly.
    protected $table = 'equipments';

    protected $fillable = [
        'inventory_code',
        'designation',
        'category',
        'sub_category',
        'brand',
        'model',
        'serial_number',
        'local_id',
        'acquisition_date',
        'acquisition_value',
        'purchase_reference_id',
        'warranty_end_date',
        'status',
        'condition',
        'photo_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => EquipmentStatus::class,
            'condition' => EquipmentCondition::class,
            'acquisition_date' => 'date',
            'warranty_end_date' => 'date',
            'acquisition_value' => 'decimal:2',
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
     * @return BelongsTo<PurchaseReference, $this>
     */
    public function purchaseReference(): BelongsTo
    {
        return $this->belongsTo(PurchaseReference::class);
    }

    /**
     * @return MorphOne<QrCode, $this>
     */
    public function qrCode(): MorphOne
    {
        return $this->morphOne(QrCode::class, 'trackable');
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * At most one active assignment per subject — the AssignmentObserver
     * closes the previous one whenever a new active assignment is created.
     */
    public function activeAssignment(): ?Assignment
    {
        return $this->assignments()
            ->whereNull('end_date')
            ->latest('start_date')
            ->first();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
