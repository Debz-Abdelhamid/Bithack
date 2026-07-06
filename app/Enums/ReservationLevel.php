<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReservationLevel: string implements HasLabel
{
    case L1 = 'l1';
    case L2 = 'l2';
    case L3 = 'l3';
    case M1 = 'm1';
    case M2 = 'm2';
    case Doctorate = 'doctorate';
    case Other = 'other';

    public function getLabel(): string
    {
        return __('patrimoine.reservation_levels.'.$this->value);
    }
}
