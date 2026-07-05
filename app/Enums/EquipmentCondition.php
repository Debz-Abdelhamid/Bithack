<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EquipmentCondition: string implements HasColor, HasLabel
{
    case New = 'new';
    case Good = 'good';
    case Worn = 'worn';
    case Damaged = 'damaged';

    public function getLabel(): string
    {
        return __('patrimoine.equipment_conditions.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'success',
            self::Good => 'info',
            self::Worn => 'warning',
            self::Damaged => 'danger',
        };
    }
}
