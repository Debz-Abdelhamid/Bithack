<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MaintenanceTicket;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class MaintenanceTicketPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MaintenanceTicket');
    }

    public function view(AuthUser $authUser, MaintenanceTicket $maintenanceTicket): bool
    {
        return $authUser->can('View:MaintenanceTicket');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MaintenanceTicket');
    }

    public function update(AuthUser $authUser, MaintenanceTicket $maintenanceTicket): bool
    {
        return $authUser->can('Update:MaintenanceTicket');
    }

    public function delete(AuthUser $authUser, MaintenanceTicket $maintenanceTicket): bool
    {
        return $authUser->can('Delete:MaintenanceTicket');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MaintenanceTicket');
    }

    public function restore(AuthUser $authUser, MaintenanceTicket $maintenanceTicket): bool
    {
        return $authUser->can('Restore:MaintenanceTicket');
    }

    public function forceDelete(AuthUser $authUser, MaintenanceTicket $maintenanceTicket): bool
    {
        return $authUser->can('ForceDelete:MaintenanceTicket');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MaintenanceTicket');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MaintenanceTicket');
    }

    public function replicate(AuthUser $authUser, MaintenanceTicket $maintenanceTicket): bool
    {
        return $authUser->can('Replicate:MaintenanceTicket');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MaintenanceTicket');
    }
}
