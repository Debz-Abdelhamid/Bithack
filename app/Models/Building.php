<?php

namespace App\Models;

use App\Enums\BuildingStatus;
use App\Models\Scopes\FacultyScope;
use Database\Factories\BuildingFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property BuildingStatus $status
 * @property float|null $latitude
 * @property float|null $longitude
 * @property-read Faculty|null $faculty
 */
#[ScopedBy(FacultyScope::class)]
class Building extends Model
{
    /** @use HasFactory<BuildingFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'faculty_id',
        'code',
        'name',
        'campus',
        'address',
        'floors_count',
        'latitude',
        'longitude',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => BuildingStatus::class,
            'floors_count' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Faculty, $this>
     */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    /**
     * @return HasMany<Local, $this>
     */
    public function locals(): HasMany
    {
        return $this->hasMany(Local::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
