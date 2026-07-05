<?php

namespace App\Models;

use App\Enums\LocalStatus;
use App\Enums\LocalType;
use App\Models\Scopes\FacultyScope;
use Database\Factories\LocalFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property LocalType $type
 * @property LocalStatus $status
 * @property-read Building $building
 */
#[ScopedBy(FacultyScope::class)]
class Local extends Model
{
    /** @use HasFactory<LocalFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'building_id',
        'code',
        'name',
        'type',
        'floor',
        'capacity',
        'surface_m2',
        'responsible_user_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => LocalType::class,
            'status' => LocalStatus::class,
            'floor' => 'integer',
            'capacity' => 'integer',
            'surface_m2' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Building, $this>
     */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
