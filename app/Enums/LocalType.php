<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LocalType: string implements HasLabel
{
    case Bureau = 'bureau';
    case SalleCours = 'salle_cours';
    case Amphi = 'amphi';
    case Labo = 'labo';
    case Atelier = 'atelier';
    case Entrepot = 'entrepot';
    case SalleReunion = 'salle_reunion';
    case Autre = 'autre';

    public function getLabel(): string
    {
        return __('patrimoine.local_types.'.$this->value);
    }
}
