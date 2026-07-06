<?php

namespace App\Filament\Resources\AcademicTerms\Pages;

use App\Filament\Resources\AcademicTerms\AcademicTermResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAcademicTerms extends ManageRecords
{
    protected static string $resource = AcademicTermResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
