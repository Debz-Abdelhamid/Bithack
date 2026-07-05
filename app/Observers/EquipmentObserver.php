<?php

namespace App\Observers;

use App\Models\Equipment;
use Illuminate\Support\Str;

/**
 * Étape 1 of the workflow — "Bien enregistré avec ID unique, QR code
 * généré" (Phases.md Phase 3). Every equipment gets a unique inventory
 * code (auto-generated when left blank, so legacy registry codes can
 * still be typed in) and an opaque QR token at creation.
 */
class EquipmentObserver
{
    public function creating(Equipment $equipment): void
    {
        if (blank($equipment->inventory_code)) {
            $equipment->inventory_code = $this->nextInventoryCode();
        }
    }

    public function created(Equipment $equipment): void
    {
        $equipment->qrCode()->create([
            'token' => (string) Str::uuid(),
            'generated_at' => now(),
        ]);
    }

    public function deleted(Equipment $equipment): void
    {
        // No FK is possible on a morph pair — clean the QR row up here so
        // a deleted asset's token stops resolving immediately.
        $equipment->qrCode()->delete();
    }

    /**
     * Sequential per-year display code (UBMA-YYYY-NNNNN), like the legacy
     * registry. Guessability is fine here — the public QR URL carries the
     * opaque token, never this code (ui-design.md §9.3). The row lock
     * serializes concurrent creates best-effort; the unique index on
     * inventory_code remains the hard guarantee.
     */
    private function nextInventoryCode(): string
    {
        $prefix = 'UBMA-'.now()->format('Y').'-';

        $last = Equipment::query()
            ->withoutGlobalScopes()
            ->where('inventory_code', 'like', $prefix.'%')
            ->orderByDesc('inventory_code')
            ->lockForUpdate()
            ->value('inventory_code');

        $next = is_string($last) ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
