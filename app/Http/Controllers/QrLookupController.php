<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\QrCode;
use Illuminate\Contracts\View\View;

/**
 * Public, read-only QR lookup (Phases.md Phase 3 DoD). The printed QR
 * encodes {APP_URL}/report/{token} — the same URL contract as the legacy
 * app, so labels printed today keep working when Phase 6 grows the
 * anomaly-report form on this page.
 *
 * Data minimization (Security.md §1/§7, Law 18-07): the page shows only
 * what someone physically standing in front of the asset already sees —
 * designation, inventory code, category, location, status. No value, no
 * serial number, no notes, no photo, no responsible person.
 */
class QrLookupController extends Controller
{
    public function __invoke(string $token): View
    {
        $qrCode = QrCode::query()->where('token', $token)->first();

        $equipment = $qrCode?->trackable;

        // Locals may carry tokens later (Schema.md §2.4 is polymorphic);
        // until that ships only equipment resolves. Unknown, dangling and
        // non-equipment tokens are all the same plain 404 — no hint about
        // whether a token ever existed.
        abort_unless($equipment instanceof Equipment, 404);

        return view('report.lookup', [
            'equipment' => $equipment->load('local.building'),
        ]);
    }
}
