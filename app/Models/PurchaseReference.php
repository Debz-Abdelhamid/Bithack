<?php

namespace App\Models;

use Database\Factories\PurchaseReferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stub toward module R7 (achats) — Schema.md §2.13. Several equipments may
 * share one purchase reference (a single order of many identical assets).
 */
class PurchaseReference extends Model
{
    /** @use HasFactory<PurchaseReferenceFactory> */
    use HasFactory;

    protected $fillable = [
        'external_purchase_id',
        'supplier',
        'order_date',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
        ];
    }

    /**
     * @return HasMany<Equipment, $this>
     */
    public function equipments(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }
}
