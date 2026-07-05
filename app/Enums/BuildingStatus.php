<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BuildingStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case UnderRenovation = 'under_renovation';
    case Decommissioned = 'decommissioned';

    public function getLabel(): string
    {
        return __('patrimoine.building_statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::UnderRenovation => 'warning',
            self::Decommissioned => 'danger',
        };
    }
}
