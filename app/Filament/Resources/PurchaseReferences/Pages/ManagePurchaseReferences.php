<?php

namespace App\Filament\Resources\PurchaseReferences\Pages;

use App\Filament\Resources\PurchaseReferences\PurchaseReferenceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePurchaseReferences extends ManageRecords
{
    protected static string $resource = PurchaseReferenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
