<?php

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Exceptions\OverlappingReservationException;
use App\Filament\Pages\RequestReservation;
use App\Filament\Pages\ReservationAvailability;
use App\Filament\Pages\TimetableBuilder;
use App\Filament\Resources\AcademicTerms\AcademicTermResource;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\Departments\Pages\ManageDepartments;
use App\Filament\Resources\RoomReservations\Pages\CreateRoomReservation;
use App\Filament\Resources\RoomReservations\Pages\ListRoomReservations;
use App\Filament\Resources\RoomReservations\RoomReservationResource;
use App\Jobs\SendReservationNotification;
use App\Models\AcademicTerm;
use App\Models\Building;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Local;
use App\Models\RoomReservation;
use App\Models\User;
use App\Support\RoleName;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->technology = Faculty::factory()->create(['name' => 'Technology']);
    $this->sciences = Faculty::factory()->create(['name' => 'Sciences']);

    $techBuilding = Building::factory()->create(['faculty_id' => $this->technology->id]);
    $sciBuilding = Building::factory()->create(['faculty_id' => $this->sciences->id]);
    $sharedBuilding = Building::factory()->create(['faculty_id' => null]);

    $this->techLocal = Local::factory()->create(['building_id' => $techBuilding->id, 'capacity' => 30]);
    $this->sciLocal = Local::factory()->create(['building_id' => $sciBuilding->id, 'capacity' => 20]);
    $this->sharedLocal = Local::factory()->create(['building_id' => $sharedBuilding->id, 'capacity' => 50]);

    $this->nextMonday = Carbon::parse('next monday')->setTime(9, 0);

    $this->techDepartment = Department::factory()->create(['faculty_id' => $this->technology->id]);
    $this->sciDepartment = Department::factory()->create(['faculty_id' => $this->sciences->id]);
    $this->term = AcademicTerm::factory()->create([
        'start_date' => $this->nextMonday->copy()->subWeek()->toDateString(),
        'end_date' => $this->nextMonday->copy()->addWeeks(6)->toDateString(),
    ]);
});

function reservationN2(Faculty $faculty): User
{
    $user = User::factory()->create(['faculty_id' => $faculty->id]);
    $user->assignRole(RoleName::RESPONSABLE_FACULTE);
    $user->forceFill(['app_authentication_secret' => 'TESTSECRETTESTSECRET'])->save();

    return $user;
}

function reservationTeacher(?Faculty $faculty = null): User
{
    $user = User::factory()->create(['faculty_id' => $faculty?->id]);
    $user->assignRole(RoleName::ENSEIGNANT);

    return $user;
}

// --- Overlap guard (Phase 5 DoD: "two users cannot double-book") ---

it('detects a confirmed overlap and lets non-overlapping slots through', function (): void {
    RoomReservation::factory()->confirmed()->create([
        'local_id' => $this->techLocal->id,
        'start_at' => $this->nextMonday,
        'end_at' => $this->nextMonday->copy()->addHour(),
    ]);

    expect(RoomReservation::hasConfirmedOverlap(
        $this->techLocal->id,
        $this->nextMonday->copy()->addMinutes(30),
        $this->nextMonday->copy()->addMinutes(90),
    ))->toBeTrue()
        ->and(RoomReservation::hasConfirmedOverlap(
            $this->techLocal->id,
            $this->nextMonday->copy()->addHours(2),
            $this->nextMonday->copy()->addHours(3),
        ))->toBeFalse();
});

it('throws when saving a confirmed reservation that overlaps another confirmed one', function (): void {
    RoomReservation::factory()->confirmed()->create([
        'local_id' => $this->techLocal->id,
        'start_at' => $this->nextMonday,
        'end_at' => $this->nextMonday->copy()->addHour(),
    ]);

    RoomReservation::factory()->confirmed()->create([
        'local_id' => $this->techLocal->id,
        'start_at' => $this->nextMonday->copy()->addMinutes(30),
        'end_at' => $this->nextMonday->copy()->addMinutes(90),
    ]);
})->throws(OverlappingReservationException::class);

it('allows two pending requests to overlap the same room/time', function (): void {
    RoomReservation::factory()->create([
        'local_id' => $this->techLocal->id,
        'start_at' => $this->nextMonday,
        'end_at' => $this->nextMonday->copy()->addHour(),
    ]);

    $second = RoomReservation::factory()->create([
        'local_id' => $this->techLocal->id,
        'start_at' => $this->nextMonday,
        'end_at' => $this->nextMonday->copy()->addHour(),
    ]);

    expect($second->exists)->toBeTrue()
        ->and(RoomReservation::query()->count())->toBe(2);
});

// --- Timetable creation (N2 own-faculty / A3 everywhere) ---

it('lets A3 create a single timetable slot, confirmed, naming the teacher', function (): void {
    $teacher = reservationTeacher($this->sciences);
    $a3 = actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE);

    $this->actingAs($a3);

    Livewire::test(CreateRoomReservation::class)
        ->fillForm([
            'local_id' => $this->sharedLocal->id,
            'teacher_user_id' => $teacher->id,
            'department_id' => $this->techDepartment->id,
            'academic_term_id' => $this->term->id,
            'module_name' => 'Algorithms',
            'level' => 'l1',
            'start_at' => $this->nextMonday,
            'end_at' => $this->nextMonday->copy()->addMinutes(90),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $reservation = RoomReservation::query()->firstOrFail();

    expect($reservation->source)->toBe(ReservationSource::Timetable)
        ->and($reservation->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->requested_by_user_id)->toBe($a3->id)
        ->and($reservation->teacher_user_id)->toBe($teacher->id)
        ->and($reservation->approved_by_user_id)->toBeNull();
});

it('lets N2 create a timetable slot for their own faculty room', function (): void {
    $teacher = reservationTeacher();
    $n2 = reservationN2($this->technology);

    $this->actingAs($n2);

    Livewire::test(CreateRoomReservation::class)
        ->fillForm([
            'local_id' => $this->techLocal->id,
            'teacher_user_id' => $teacher->id,
            'department_id' => $this->techDepartment->id,
            'academic_term_id' => $this->term->id,
            'module_name' => 'Networks',
            'level' => 'l2',
            'start_at' => $this->nextMonday,
            'end_at' => $this->nextMonday->copy()->addMinutes(90),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(RoomReservation::query()->where('requested_by_user_id', $n2->id)->exists())->toBeTrue();
});

it("rejects N2 posting another faculty's room id for a timetable slot", function (): void {
    $teacher = reservationTeacher();

    $this->actingAs(reservationN2($this->technology));

    Livewire::test(CreateRoomReservation::class)
        ->fillForm([
            'local_id' => $this->sciLocal->id,
            'teacher_user_id' => $teacher->id,
            'module_name' => 'Networks',
            'level' => 'l2',
            'start_at' => $this->nextMonday,
            'end_at' => $this->nextMonday->copy()->addMinutes(90),
        ])
        ->call('create')
        ->assertHasFormErrors(['local_id']);

    expect(RoomReservation::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('rejects N2 entering a timetable slot for a shared/central room — A3-only', function (): void {
    $teacher = reservationTeacher();

    $this->actingAs(reservationN2($this->technology));

    Livewire::test(CreateRoomReservation::class)
        ->fillForm([
            'local_id' => $this->sharedLocal->id,
            'teacher_user_id' => $teacher->id,
            'module_name' => 'Networks',
            'level' => 'l2',
            'start_at' => $this->nextMonday,
            'end_at' => $this->nextMonday->copy()->addMinutes(90),
        ])
        ->call('create')
        ->assertHasFormErrors(['local_id']);
});

it('generates one row per week for a recurring timetable slot, bounded by the academic term end date', function (): void {
    $teacher = reservationTeacher();
    $shortTerm = AcademicTerm::factory()->create([
        'start_date' => $this->nextMonday->copy()->subDay()->toDateString(),
        'end_date' => $this->nextMonday->copy()->addWeeks(3)->toDateString(),
    ]);

    $this->actingAs(actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE));

    Livewire::test(CreateRoomReservation::class)
        ->fillForm([
            'local_id' => $this->techLocal->id,
            'teacher_user_id' => $teacher->id,
            'department_id' => $this->techDepartment->id,
            'academic_term_id' => $shortTerm->id,
            'module_name' => 'Algorithms',
            'level' => 'l1',
            'start_at' => $this->nextMonday,
            'end_at' => $this->nextMonday->copy()->addMinutes(90),
            'repeat_weekly' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $rows = RoomReservation::query()->orderBy('start_at')->get();

    expect($rows)->toHaveCount(4)
        ->and($rows->pluck('recurring_group_id')->unique())->toHaveCount(1)
        ->and($rows->last()->start_at->toDateString())->toBe($this->nextMonday->copy()->addWeeks(3)->toDateString());
});

it('creates nothing from a recurring series if any occurrence conflicts', function (): void {
    $teacher = reservationTeacher();
    $shortTerm = AcademicTerm::factory()->create([
        'start_date' => $this->nextMonday->copy()->subDay()->toDateString(),
        'end_date' => $this->nextMonday->copy()->addWeeks(3)->toDateString(),
    ]);

    // Blocks the 3rd occurrence (two weeks out).
    RoomReservation::factory()->confirmed()->create([
        'local_id' => $this->techLocal->id,
        'start_at' => $this->nextMonday->copy()->addWeeks(2),
        'end_at' => $this->nextMonday->copy()->addWeeks(2)->addMinutes(90),
    ]);

    $this->actingAs(actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE));

    Livewire::test(CreateRoomReservation::class)
        ->fillForm([
            'local_id' => $this->techLocal->id,
            'teacher_user_id' => $teacher->id,
            'department_id' => $this->techDepartment->id,
            'academic_term_id' => $shortTerm->id,
            'module_name' => 'Algorithms',
            'level' => 'l1',
            'start_at' => $this->nextMonday,
            'end_at' => $this->nextMonday->copy()->addMinutes(90),
            'repeat_weekly' => true,
        ])
        ->call('create');

    // Only the pre-existing blocker remains — the series was never persisted.
    expect(RoomReservation::query()->count())->toBe(1);
});

// --- Confirm / Reject (ReservationApprovalService, Phase 5 DoD) ---

it('lets N2 confirm a pending request in their scope and notifies the requester', function (): void {
    Bus::fake();

    $teacher = reservationTeacher($this->sciences);
    $request = RoomReservation::factory()->create([
        'local_id' => $this->techLocal->id,
        'requested_by_user_id' => $teacher->id,
        'teacher_user_id' => $teacher->id,
        'start_at' => $this->nextMonday,
        'end_at' => $this->nextMonday->copy()->addHour(),
    ]);

    $this->actingAs(reservationN2($this->technology));

    Livewire::test(ListRoomReservations::class)
        ->callAction(TestAction::make('confirm')->table($request));

    expect($request->refresh()->status)->toBe(ReservationStatus::Confirmed);

    Bus::assertDispatched(SendReservationNotification::class, fn (SendReservationNotification $job): bool => $job->userId === $teacher->id);
});

it('auto-rejects other overlapping pending requests when one is confirmed', function (): void {
    Bus::fake();

    $winner = RoomReservation::factory()->create([
        'local_id' => $this->techLocal->id,
        'start_at' => $this->nextMonday,
        'end_at' => $this->nextMonday->copy()->addHour(),
    ]);

    $loser = RoomReservation::factory()->create([
        'local_id' => $this->techLocal->id,
        'start_at' => $this->nextMonday->copy()->addMinutes(30),
        'end_at' => $this->nextMonday->copy()->addMinutes(90),
    ]);

    $untouched = RoomReservation::factory()->create([
        'local_id' => $this->techLocal->id,
        'start_at' => $this->nextMonday->copy()->addHours(3),
        'end_at' => $this->nextMonday->copy()->addHours(4),
    ]);

    $this->actingAs(actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE));

    Livewire::test(ListRoomReservations::class)
        ->callAction(TestAction::make('confirm')->table($winner));

    expect($winner->refresh()->status)->toBe(ReservationStatus::Confirmed)
        ->and($loser->refresh()->status)->toBe(ReservationStatus::Rejected)
        ->and($untouched->refresh()->status)->toBe(ReservationStatus::Pending);
});

it('blocks confirmation if the room was already booked in the meantime', function (): void {
    $request = RoomReservation::factory()->create([
        'local_id' => $this->techLocal->id,
        'start_at' => $this->nextMonday,
        'end_at' => $this->nextMonday->copy()->addHour(),
    ]);

    RoomReservation::factory()->confirmed()->create([
        'local_id' => $this->techLocal->id,
        'start_at' => $this->nextMonday,
        'end_at' => $this->nextMonday->copy()->addHour(),
    ]);

    $this->actingAs(actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE));

    Livewire::test(ListRoomReservations::class)
        ->callAction(TestAction::make('confirm')->table($request));

    expect($request->refresh()->status)->toBe(ReservationStatus::Pending);
});

it('lets N2 reject a pending request with a reason', function (): void {
    Bus::fake();

    $teacher = reservationTeacher($this->sciences);
    $request = RoomReservation::factory()->create([
        'local_id' => $this->techLocal->id,
        'requested_by_user_id' => $teacher->id,
        'start_at' => $this->nextMonday,
        'end_at' => $this->nextMonday->copy()->addHour(),
    ]);

    $this->actingAs(reservationN2($this->technology));

    Livewire::test(ListRoomReservations::class)
        ->callAction(TestAction::make('reject')->table($request), data: ['reason' => 'Room needed for exams']);

    expect($request->refresh()->status)->toBe(ReservationStatus::Rejected);

    Bus::assertDispatched(SendReservationNotification::class);
});

it("denies N2 the approve ability on another faculty's pending request", function (): void {
    $request = RoomReservation::factory()->create([
        'local_id' => $this->sciLocal->id,
        'start_at' => $this->nextMonday,
        'end_at' => $this->nextMonday->copy()->addHour(),
    ]);

    $n2 = reservationN2($this->technology);

    // Out of scope on two independent levels: the policy (tested directly —
    // Filament's table-action mounting resolves records through the same
    // scoped query and would throw ActionNotResolvableException before ever
    // reaching visibility, which is the stronger guarantee) and the listing.
    expect($n2->can('approve', $request))->toBeFalse();

    $this->actingAs($n2);

    expect(RoomReservation::query()->whereKey($request->id)->exists())->toBeFalse();
});

// --- Enseignant ad-hoc request page ---

it('lets an Enseignant submit an ad-hoc request campus-wide and notifies the approver', function (): void {
    Bus::fake();

    $teacher = reservationTeacher($this->sciences);
    $roomN2 = reservationN2($this->technology); // owns techLocal's building — the approver to notify

    $this->actingAs($teacher);

    Livewire::test(RequestReservation::class)
        ->fillForm([
            'local_id' => $this->techLocal->id,
            'purpose' => 'Makeup class',
            'start_at' => $this->nextMonday,
            'end_at' => $this->nextMonday->copy()->addHour(),
        ])
        ->call('submit');

    $reservation = RoomReservation::query()->withoutGlobalScopes()->firstOrFail();

    expect($reservation->source)->toBe(ReservationSource::Request)
        ->and($reservation->status)->toBe(ReservationStatus::Pending)
        ->and($reservation->requested_by_user_id)->toBe($teacher->id)
        ->and($reservation->teacher_user_id)->toBe($teacher->id);

    Bus::assertDispatched(SendReservationNotification::class, fn (SendReservationNotification $job): bool => $job->userId === $roomN2->id);
});

it('requires a purpose for a non-course ad-hoc request', function (): void {
    $this->actingAs(reservationTeacher());

    Livewire::test(RequestReservation::class)
        ->fillForm([
            'local_id' => $this->techLocal->id,
            'start_at' => $this->nextMonday,
            'end_at' => $this->nextMonday->copy()->addHour(),
        ])
        ->call('submit')
        ->assertHasFormErrors(['purpose']);
});

it('requires a level for a course ad-hoc request', function (): void {
    $this->actingAs(reservationTeacher());

    Livewire::test(RequestReservation::class)
        ->fillForm([
            'local_id' => $this->techLocal->id,
            'module_name' => 'Physics',
            'start_at' => $this->nextMonday,
            'end_at' => $this->nextMonday->copy()->addHour(),
        ])
        ->call('submit')
        ->assertHasFormErrors(['level']);
});

it('refuses an ad-hoc request for a room already booked at that time', function (): void {
    RoomReservation::factory()->confirmed()->create([
        'local_id' => $this->techLocal->id,
        'start_at' => $this->nextMonday,
        'end_at' => $this->nextMonday->copy()->addHour(),
    ]);

    $this->actingAs(reservationTeacher());

    Livewire::test(RequestReservation::class)
        ->fillForm([
            'local_id' => $this->techLocal->id,
            'purpose' => 'Makeup class',
            'start_at' => $this->nextMonday,
            'end_at' => $this->nextMonday->copy()->addHour(),
        ])
        ->call('submit');

    expect(RoomReservation::query()->withoutGlobalScopes()->where('status', ReservationStatus::Pending)->count())->toBe(0);
});

it('rate limits ad-hoc requests per user per hour', function (): void {
    config()->set('patrimo.reservations.request_max_per_hour', 1);

    $teacher = reservationTeacher();
    $this->actingAs($teacher);

    Livewire::test(RequestReservation::class)
        ->fillForm([
            'local_id' => $this->techLocal->id,
            'purpose' => 'First request',
            'start_at' => $this->nextMonday,
            'end_at' => $this->nextMonday->copy()->addHour(),
        ])
        ->call('submit');

    Livewire::test(RequestReservation::class)
        ->fillForm([
            'local_id' => $this->techLocal->id,
            'purpose' => 'Second request',
            'start_at' => $this->nextMonday->copy()->addHours(5),
            'end_at' => $this->nextMonday->copy()->addHours(6),
        ])
        ->call('submit');

    expect(RoomReservation::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('lets an Enseignant cancel their own pending request but not someone else\'s', function (): void {
    $teacher = reservationTeacher();
    $other = reservationTeacher();

    $own = RoomReservation::factory()->create(['local_id' => $this->techLocal->id, 'requested_by_user_id' => $teacher->id]);
    $foreign = RoomReservation::factory()->create(['local_id' => $this->techLocal->id, 'requested_by_user_id' => $other->id]);

    $this->actingAs($teacher);

    Livewire::test(RequestReservation::class)->call('cancel', $own->id);

    expect($own->refresh()->status)->toBe(ReservationStatus::Cancelled);

    Livewire::test(RequestReservation::class)
        ->call('cancel', $foreign->id)
        ->assertForbidden();
});

// --- RBAC / page access ---

it('denies the admin reservations resource to tout_utilisateur and Enseignant', function (): void {
    $this->actingAs(actingUserWithRole(RoleName::TOUT_UTILISATEUR));
    $this->get(RoomReservationResource::getUrl('index'))->assertForbidden();

    $this->actingAs(reservationTeacher());
    $this->get(RoomReservationResource::getUrl('index'))->assertForbidden();
});

it('allows everyone to view the read-only availability grid', function (): void {
    $this->actingAs(actingUserWithRole(RoleName::TOUT_UTILISATEUR));

    $this->get(ReservationAvailability::getUrl())->assertOk();
});

it('only shows the ad-hoc request page nav to Enseignant, not to N2/A3', function (): void {
    $this->actingAs(reservationTeacher());
    expect(RequestReservation::canAccess())->toBeTrue();

    $this->actingAs(reservationN2($this->technology));
    expect(RequestReservation::canAccess())->toBeFalse();

    $this->actingAs(actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE));
    expect(RequestReservation::canAccess())->toBeFalse();
});

it('shows A3 every reservation regardless of faculty — unscoped', function (): void {
    RoomReservation::factory()->create(['local_id' => $this->techLocal->id]);
    RoomReservation::factory()->create(['local_id' => $this->sciLocal->id]);
    RoomReservation::factory()->create(['local_id' => $this->sharedLocal->id]);

    $this->actingAs(actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE));

    expect(RoomReservation::query()->count())->toBe(3);
});

// --- Availability grid content ---

it('only surfaces confirmed reservations on the availability grid', function (): void {
    RoomReservation::factory()->confirmed()->create([
        'local_id' => $this->techLocal->id,
        'start_at' => $this->nextMonday,
        'end_at' => $this->nextMonday->copy()->addHour(),
        'module_name' => 'Visible Course',
    ]);

    RoomReservation::factory()->create([
        'local_id' => $this->techLocal->id,
        'start_at' => $this->nextMonday->copy()->addHours(3),
        'end_at' => $this->nextMonday->copy()->addHours(4),
        'purpose' => 'Hidden pending request',
    ]);

    $this->actingAs(actingUserWithRole(RoleName::TOUT_UTILISATEUR));

    Livewire::test(ReservationAvailability::class)
        ->set('selectedLocalId', $this->techLocal->id)
        ->set('weekStart', $this->nextMonday->copy()->startOfWeek()->toDateString())
        ->assertSee('Visible Course')
        ->assertDontSee('Hidden pending request');
});

// --- Departments (Phase 5 addendum, 2026-07-06) ---

it('lets A3 create a department for any faculty', function (): void {
    $this->actingAs(actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE));

    Livewire::test(ManageDepartments::class)
        ->callAction('create', [
            'faculty_id' => $this->sciences->id,
            'name' => 'Chemistry',
        ])
        ->assertHasNoActionErrors();

    expect(Department::query()->where('name', 'Chemistry')->exists())->toBeTrue();
});

it('lets N2 create a department for their own faculty only', function (): void {
    $this->actingAs(reservationN2($this->technology));

    Livewire::test(ManageDepartments::class)
        ->callAction('create', [
            'faculty_id' => $this->technology->id,
            'name' => 'Robotics',
        ])
        ->assertHasNoActionErrors();

    expect(Department::query()->where('name', 'Robotics')->exists())->toBeTrue();
});

it('blocks N2 from creating a department for another faculty', function (): void {
    $this->actingAs(reservationN2($this->technology));

    Livewire::test(ManageDepartments::class)
        ->callAction('create', [
            'faculty_id' => $this->sciences->id,
            'name' => 'Forged Department',
        ])
        ->assertHasActionErrors(['faculty_id']);

    expect(Department::query()->withoutGlobalScopes()->where('name', 'Forged Department')->exists())->toBeFalse();
});

it("scopes N2's department list to their own faculty", function (): void {
    $this->actingAs(reservationN2($this->technology));

    $names = Department::query()->pluck('name')->all();

    expect($names)->toBe([$this->techDepartment->name]);
});

it('denies tout_utilisateur and Enseignant the departments resource', function (): void {
    $this->actingAs(actingUserWithRole(RoleName::TOUT_UTILISATEUR));
    $this->get(DepartmentResource::getUrl('index'))->assertForbidden();

    $this->actingAs(reservationTeacher());
    $this->get(DepartmentResource::getUrl('index'))->assertForbidden();
});

// --- Academic terms (Phase 5 addendum, 2026-07-06) ---

it('lets A3 manage academic terms; N2 is read-only', function (): void {
    $this->actingAs(actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE));
    $this->get(AcademicTermResource::getUrl('index'))->assertOk();

    $a3 = actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE);
    expect($a3->can('create', AcademicTerm::class))->toBeTrue();

    $n2 = reservationN2($this->technology);
    expect($n2->can('viewAny', AcademicTerm::class))->toBeTrue()
        ->and($n2->can('create', AcademicTerm::class))->toBeFalse();
});

it('auto-generates an academic term label when left blank', function (): void {
    $term = AcademicTerm::factory()->create([
        'academic_year' => '2030-2031',
        'semester' => 2,
        'label' => null,
    ]);

    expect($term->label)->toBe('2030-2031 — Semester 2');
});

// --- Timetable builder grid (Phase 5 addendum, 2026-07-06) ---

it('gates the timetable builder page to manageTimetable holders only', function (): void {
    $this->actingAs(actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE));
    expect(TimetableBuilder::canAccess())->toBeTrue();

    $this->actingAs(reservationN2($this->technology));
    expect(TimetableBuilder::canAccess())->toBeTrue();

    $this->actingAs(reservationTeacher());
    expect(TimetableBuilder::canAccess())->toBeFalse();

    $this->actingAs(actingUserWithRole(RoleName::TOUT_UTILISATEUR));
    expect(TimetableBuilder::canAccess())->toBeFalse();
});

it('places an existing confirmed slot in the correct grid cell', function (): void {
    $teacher = reservationTeacher();
    $monday8am = $this->nextMonday->copy()->setTime(8, 0);

    RoomReservation::factory()->confirmed()->create([
        'local_id' => $this->techLocal->id,
        'source' => ReservationSource::Timetable,
        'department_id' => $this->techDepartment->id,
        'academic_term_id' => $this->term->id,
        'teacher_user_id' => $teacher->id,
        'module_name' => 'Grid Slot Course',
        'start_at' => $monday8am,
        'end_at' => $monday8am->copy()->addMinutes(90),
    ]);

    $this->actingAs(actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE));

    Livewire::test(TimetableBuilder::class)
        ->set('departmentId', $this->techDepartment->id)
        ->set('academicTermId', $this->term->id)
        ->assertSee('Grid Slot Course');
});

it('adds a weekly slot from the grid, bounded by the term end date', function (): void {
    $teacher = reservationTeacher();

    $this->actingAs(actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE));

    Livewire::test(TimetableBuilder::class)
        ->set('departmentId', $this->techDepartment->id)
        ->set('academicTermId', $this->term->id)
        ->fillForm([
            'local_id' => $this->techLocal->id,
            'teacher_user_id' => $teacher->id,
            'module_name' => 'New Grid Course',
            'level' => 'l1',
            'day' => (string) Carbon::MONDAY,
            'time_slot' => '08:00 - 09:30',
        ])
        ->call('submit');

    $rows = RoomReservation::query()->where('module_name', 'New Grid Course')->get();

    expect($rows)->not->toBeEmpty()
        ->and($rows->every(fn (RoomReservation $r): bool => $r->academic_term_id === $this->term->id))->toBeTrue()
        ->and($rows->last()->start_at->toDateString())->toBe($this->term->end_date->toDateString());
});

it('cancels the whole recurring series, not just one occurrence', function (): void {
    $teacher = reservationTeacher();
    $groupId = (string) Str::uuid();

    $first = RoomReservation::factory()->confirmed()->create([
        'local_id' => $this->techLocal->id,
        'department_id' => $this->techDepartment->id,
        'academic_term_id' => $this->term->id,
        'teacher_user_id' => $teacher->id,
        'recurring_group_id' => $groupId,
        'start_at' => $this->nextMonday,
        'end_at' => $this->nextMonday->copy()->addMinutes(90),
    ]);

    $second = RoomReservation::factory()->confirmed()->create([
        'local_id' => $this->techLocal->id,
        'department_id' => $this->techDepartment->id,
        'academic_term_id' => $this->term->id,
        'teacher_user_id' => $teacher->id,
        'recurring_group_id' => $groupId,
        'start_at' => $this->nextMonday->copy()->addWeek(),
        'end_at' => $this->nextMonday->copy()->addWeek()->addMinutes(90),
    ]);

    $this->actingAs(actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE));

    Livewire::test(TimetableBuilder::class)
        ->set('departmentId', $this->techDepartment->id)
        ->set('academicTermId', $this->term->id)
        ->call('cancelSlot', $first->id);

    expect($first->refresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($second->refresh()->status)->toBe(ReservationStatus::Cancelled);
});
