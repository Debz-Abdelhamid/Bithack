<?php

namespace App\Http\Controllers;

use App\Models\Local;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Printable A4 inventory list for a room (owner request, 2026-07-08) —
 * same new-tab → auto-print flow as the equipment QR label. Gated on the
 * same `view` ability as the room itself (a read-only report, not an
 * operational action like PrintLabel:Equipment, so no separate permission).
 */
class LocalEquipmentListController extends Controller
{
    public function __invoke(Local $local): View
    {
        Gate::authorize('view', $local);

        return view('locals.equipment-list', [
            'local' => $local->load('building'),
            'equipments' => $local->equipments()->orderBy('designation')->get(),
        ]);
    }
}
