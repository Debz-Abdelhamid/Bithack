<?php

namespace App\Models;

use Database\Factories\QrCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One opaque token per trackable asset (Schema.md §2.4). The token — never
 * a sequential id — is the only identifier that leaves the system (printed
 * QR labels, public lookup URL).
 *
 * @property string $token
 * @property bool $printed
 * @property-read Equipment|Local|null $trackable
 */
class QrCode extends Model
{
    /** @use HasFactory<QrCodeFactory> */
    use HasFactory;

    protected $fillable = [
        'token',
        'generated_at',
        'printed',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'printed' => 'boolean',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function trackable(): MorphTo
    {
        return $this->morphTo();
    }
}
