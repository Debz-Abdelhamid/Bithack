<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Printable A4 QR label (ui-design.md §6): new tab → A4 card with the QR
 * and the monospace inventory code → auto window.print().
 *
 * Deliberate GET side effect: rendering the label records the print event
 * (printed flag + audit trail). The old-app flow is a synchronous new-tab
 * navigation — which cannot POST — and the flag is an idempotent
 * operational marker, not business data. Route is auth + policy + throttle
 * protected; route-model binding goes through FacultyScope, so a
 * faculty-bound user gets a 404 on foreign equipment.
 */
class EquipmentLabelController extends Controller
{
    public function __invoke(Equipment $equipment): View
    {
        Gate::authorize('printLabel', $equipment);

        $qrCode = $equipment->qrCode;

        abort_if($qrCode === null, 404);

        if (! $qrCode->printed) {
            $qrCode->update(['printed' => true]);
        }

        activity('patrimoine')
            ->causedBy(auth()->user())
            ->performedOn($equipment)
            ->withProperties(['inventory_code' => $equipment->inventory_code])
            ->log('label_printed');

        return view('equipments.label', [
            'equipment' => $equipment,
            'lookupUrl' => route('qr.lookup', ['token' => $qrCode->token]),
        ]);
    }
}
