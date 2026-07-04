<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ServiceType: string implements HasLabel
{
    case Service = 'service';
    case Labo = 'labo';
    case Bureau = 'bureau';

    public function getLabel(): string
    {
        return __('patrimoine.service_types.'.$this->value);
    }
}
