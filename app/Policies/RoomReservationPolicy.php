<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RoomReservation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class RoomReservationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RoomReservation');
    }

    public function view(AuthUser $authUser, RoomReservation $roomReservation): bool
    {
        return $authUser->can('View:RoomReservation');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RoomReservation');
    }

    public function update(AuthUser $authUser, RoomReservation $roomReservation): bool
    {
        return $authUser->can('Update:RoomReservation');
    }

    public function delete(AuthUser $authUser, RoomReservation $roomReservation): bool
    {
        return $authUser->can('Delete:RoomReservation');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RoomReservation');
    }

    public function restore(AuthUser $authUser, RoomReservation $roomReservation): bool
    {
        return $authUser->can('Restore:RoomReservation');
    }

    public function forceDelete(AuthUser $authUser, RoomReservation $roomReservation): bool
    {
        return $authUser->can('ForceDelete:RoomReservation');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RoomReservation');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RoomReservation');
    }

    public function replicate(AuthUser $authUser, RoomReservation $roomReservation): bool
    {
        return $authUser->can('Replicate:RoomReservation');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RoomReservation');
    }

    /**
     * Gates the `timetable`-mode form fields (teacher picker, recurrence) —
     * N2 (their own faculty's rooms, enforced by FacultyScope + the form's
     * scoped-exists rule) and A3 (central/shared rooms, and everywhere).
     */
    public function manageTimetable(AuthUser $authUser): bool
    {
        return $authUser->can('ManageTimetable:RoomReservation');
    }

    /**
     * Confirm/Reject a pending `request` row. Table actions mount records
     * through Livewire, not Laravel route-model binding — unlike the Edit
     * page, that path isn't proven to re-apply FacultyScope on every
     * Filament version, so this checks faculty membership explicitly
     * rather than trusting query scoping alone.
     */
    public function approve(AuthUser $authUser, RoomReservation $roomReservation): bool
    {
        return $authUser->can('Approve:RoomReservation') && $this->withinFacultyScope($authUser, $roomReservation);
    }

    /**
     * Cancel a `request` row: its own requester (Enseignant), or anyone
     * holding the broader Update ability (A3/N2, within their faculty).
     */
    public function cancel(AuthUser $authUser, RoomReservation $roomReservation): bool
    {
        if ($authUser->can('Update:RoomReservation') && $this->withinFacultyScope($authUser, $roomReservation)) {
            return true;
        }

        return $authUser->can('Cancel:RoomReservation')
            && $authUser->getAuthIdentifier() === $roomReservation->requested_by_user_id;
    }

    /**
     * True for A3/N3/admin (no faculty_id, or the ViewAcrossFaculties
     * escape hatch) and for an N2 whose OWN faculty owns the room's
     * building — never for a shared/central room, which is A3's
     * responsibility (Security.md §3).
     */
    private function withinFacultyScope(AuthUser $authUser, RoomReservation $roomReservation): bool
    {
        if (! $authUser instanceof User || $authUser->faculty_id === null) {
            return true;
        }

        if ($authUser->can('ViewAcrossFaculties')) {
            return true;
        }

        return $roomReservation->local->building->faculty_id === $authUser->faculty_id;
    }
}
