<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EquipmentStatus: string implements HasColor, HasLabel
{
    case InService = 'in_service';
    case UnderRepair = 'under_repair';
    case Decommissioned = 'decommissioned';
    case Lost = 'lost';

    public function getLabel(): string
    {
        return __('patrimoine.equipment_statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InService => 'success',
            self::UnderRepair => 'warning',
            self::Decommissioned => 'gray',
            self::Lost => 'danger',
        };
    }
}
