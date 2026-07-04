<?php

namespace App\Models;

use App\Enums\ServiceType;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'faculty_id',
        'name',
        'type',
        'responsible_user_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => ServiceType::class,
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
     * @return BelongsTo<User, $this>
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
