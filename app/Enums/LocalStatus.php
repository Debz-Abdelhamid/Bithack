<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LocalStatus: string implements HasColor, HasLabel
{
    case Available = 'available';
    case Occupied = 'occupied';
    case UnderMaintenance = 'under_maintenance';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return __('patrimoine.local_statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Occupied => 'info',
            self::UnderMaintenance => 'warning',
            self::Closed => 'danger',
        };
    }
}
