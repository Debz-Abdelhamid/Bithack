<?php

namespace Database\Factories;

use App\Models\QrCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Rarely needed directly — EquipmentObserver creates the QR row on
 * equipment creation. Kept for completeness (Claude.md §8).
 *
 * @extends Factory<QrCode>
 */
class QrCodeFactory extends Factory
{
    protected $model = QrCode::class;

    public function definition(): array
    {
        return [
            'token' => (string) Str::uuid(),
            'generated_at' => now(),
            'printed' => false,
        ];
    }
}
