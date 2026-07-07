<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Schema.md §2.9. Independent from the parent ticket's own status —
 * a ticket can be `in_progress` while its intervention is still `planned`
 * (technician assigned, not yet on site).
 */
enum InterventionStatus: string implements HasColor, HasLabel
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __('patrimoine.intervention_statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Planned => 'warning',
            self::InProgress => 'primary',
            self::Done => 'success',
            self::Cancelled => 'gray',
        };
    }
}
