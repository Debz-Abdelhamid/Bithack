<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Schema.md §2.8. `qr_scan` is the only source Phase 6 creates
 * automatically; `manual`/`auto` exist for A3-entered reports and a
 * future automated-monitoring integration, neither built yet.
 */
enum TicketSource: string implements HasLabel
{
    case QrScan = 'qr_scan';
    case Manual = 'manual';
    case Auto = 'auto';

    public function getLabel(): string
    {
        return __('patrimoine.ticket_sources.'.$this->value);
    }
}
