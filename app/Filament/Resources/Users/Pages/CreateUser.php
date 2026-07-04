<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Admin-provisioned accounts are pre-verified: the administrator typing an
     * institutional address IS the trust step (Security.md §2). Only
     * self-registered accounts go through the email verification flow.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['email_verified_at'] = now();

        return $data;
    }
}
