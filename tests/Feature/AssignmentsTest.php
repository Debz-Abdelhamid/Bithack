<?php

use App\Filament\Resources\Assignments\AssignmentResource;
use App\Filament\Resources\Assignments\Pages\CreateAssignment;
use App\Filament\Resources\Assignments\Pages\ListAssignments;
use App\Filament\Resources\Equipments\EquipmentResource;
use App\Filament\Resources\Equipments\Pages\ViewEquipment;
use App\Filament\Resources\Equipments\RelationManagers\AssignmentsRelationManager;
use App\Models\Assignment;
use App\Models\Building;
use App\Models\Equipment;
use App\Models\Faculty;
use App\Models\Local;
use App\Models\Service;
use App\Models\User;
use App\Support\RoleName;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->technology = Faculty::factory()->create(['name' => 'Technology']);
    $this->sciences = Faculty::factory()->create(['name' => 'Sciences']);

    $techBuilding = Building::factory()->create(['faculty_id' => $this->technology->id]);
    $sciBuilding = Building::factory()->create(['faculty_id' => $this->sciences->id]);

    $this->techLocal = Local::factory()->create(['building_id' => $techBuilding->id]);
    $this->sciLocal = Local::factory()->create(['building_id' => $sciBuilding->id]);

    $this->service = Service::factory()->create(['name' => 'IT Support']);
});

function assignmentN2(Faculty $faculty): User
{
    $user = User::factory()->create(['faculty_id' => $faculty->id]);
    $user->assignRole(RoleName::RESPONSABLE_FACULTE);
    $user->forceFill(['app_authentication_secret' => 'TESTSECRETTESTSECRET'])->save();

    return $user;
}

// --- Étape 2: "Affectation enregistrée avec date et responsable" ---

it('lets A3 assign an equipment to a service, recording the assigner', function (): void {
    $equipment = Equipment::factory()->create();
    $a3 = actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE);

    $this->actingAs($a3);

    Livewire::test(CreateAssignment::class)
        ->fillForm([
            'equipment_id' => $equipment->id,
            'service_id' => $this->service->id,
            'start_date' => '2026-07-06',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $assignment = Assignment::query()->firstOrFail();

    expect($assignment->equipment_id)->toBe($equipment->id)
        ->and($assignment->service_id)->toBe($this->service->id)
        ->and($assignment->assigned_by_user_id)->toBe($a3->id)
        ->and($assignment->isActive())->toBeTrue();
});

it('closes the previous active assignment when a new one is created — history preserved', function (): void {
    $equipment = Equipment::factory()->create();
    $otherService = Service::factory()->create();

    $first = Assignment::factory()->create([
        'equipment_id' => $equipment->id,
        'service_id' => $this->service->id,
        'start_date' => '2026-01-10',
    ]);

    $second = Assignment::factory()->create([
        'equipment_id' => $equipment->id,
        'service_id' => $otherService->id,
        'start_date' => '2026-07-01',
    ]);

    expect($first->refresh()->end_date?->toDateString())->toBe('2026-07-01')
        ->and($second->refresh()->isActive())->toBeTrue()
        ->and(Assignment::query()->count())->toBe(2);
});

it('moves the equipment when the assignment carries a destination room', function (): void {
    $equipment = Equipment::factory()->create(['local_id' => $this->techLocal->id]);

    Assignment::factory()->create([
        'equipment_id' => $equipment->id,
        'local_id' => $this->sciLocal->id,
        'service_id' => $this->service->id,
    ]);

    expect($equipment->refresh()->local_id)->toBe($this->sciLocal->id);
});

it('rejects an assignment with no subject at all', function (): void {
    $this->actingAs(actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE));

    Livewire::test(CreateAssignment::class)
        ->fillForm([
            'service_id' => $this->service->id,
            'start_date' => '2026-07-06',
        ])
        ->call('create')
        ->assertHasFormErrors(['equipment_id', 'local_id']);
});

it('rejects an equipment assignment pointing nowhere (no room, service or person)', function (): void {
    $equipment = Equipment::factory()->create();

    $this->actingAs(actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE));

    Livewire::test(CreateAssignment::class)
        ->fillForm([
            'equipment_id' => $equipment->id,
            'start_date' => '2026-07-06',
        ])
        ->call('create')
        ->assertHasFormErrors(['start_date']);
});

// --- RBAC + faculty scope ---

it('lets N2 assign an equipment inside their own faculty', function (): void {
    $equipment = Equipment::factory()->create(['local_id' => $this->techLocal->id]);
    $n2 = assignmentN2($this->technology);

    $this->actingAs($n2);

    Livewire::test(CreateAssignment::class)
        ->fillForm([
            'equipment_id' => $equipment->id,
            'service_id' => $this->service->id,
            'start_date' => '2026-07-06',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Assignment::query()->withoutGlobalScopes()->value('assigned_by_user_id'))->toBe($n2->id);
});

it("blocks N2 from assigning another faculty's equipment — server-side", function (): void {
    $foreign = Equipment::factory()->create(['local_id' => $this->sciLocal->id]);

    $this->actingAs(assignmentN2($this->technology));

    Livewire::test(CreateAssignment::class)
        ->fillForm([
            'equipment_id' => $foreign->id,
            'service_id' => $this->service->id,
            'start_date' => '2026-07-06',
        ])
        ->call('create')
        ->assertHasFormErrors(['equipment_id']);

    expect(Assignment::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('scopes the N2 assignment list to their faculty subjects', function (): void {
    $techEq = Equipment::factory()->create(['local_id' => $this->techLocal->id]);
    $sciEq = Equipment::factory()->create(['local_id' => $this->sciLocal->id]);
    $unplacedEq = Equipment::factory()->create(['local_id' => null]);

    $visible = Assignment::factory()->create(['equipment_id' => $techEq->id, 'service_id' => $this->service->id]);
    $foreign = Assignment::factory()->create(['equipment_id' => $sciEq->id, 'service_id' => $this->service->id]);
    $central = Assignment::factory()->create(['equipment_id' => $unplacedEq->id, 'service_id' => $this->service->id]);

    $this->actingAs(assignmentN2($this->technology));

    $ids = Assignment::query()->pluck('id')->sort()->values()->all();

    expect($ids)->toBe(collect([$visible->id, $central->id])->sort()->values()->all());

    $this->get(AssignmentResource::getUrl('edit', ['record' => $foreign]))->assertNotFound();
});

it('keeps deletion A3-only — N2 can update but never delete', function (): void {
    $equipment = Equipment::factory()->create(['local_id' => $this->techLocal->id]);
    $assignment = Assignment::factory()->create(['equipment_id' => $equipment->id, 'service_id' => $this->service->id]);

    $n2 = assignmentN2($this->technology);
    $a3 = actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE);

    expect($n2->can('update', $assignment))->toBeTrue()
        ->and($n2->can('delete', $assignment))->toBeFalse()
        ->and($a3->can('delete', $assignment))->toBeTrue();
});

it('denies tout_utilisateur the assignments resource', function (): void {
    $this->actingAs(actingUserWithRole(RoleName::TOUT_UTILISATEUR));

    $this->get(AssignmentResource::getUrl('index'))->assertForbidden();
});

// --- Revoke + history views ---

it('revokes an active assignment from the list — closed, not deleted', function (): void {
    $equipment = Equipment::factory()->create();
    $assignment = Assignment::factory()->create(['equipment_id' => $equipment->id, 'service_id' => $this->service->id]);

    $this->actingAs(actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE));

    Livewire::test(ListAssignments::class)
        ->callAction(TestAction::make('revoke')->table($assignment));

    expect($assignment->refresh()->end_date?->toDateString())->toBe(today()->toDateString())
        ->and(Assignment::query()->count())->toBe(1);
});

it('shows the assignment history on the equipment page', function (): void {
    $equipment = Equipment::factory()->create();
    $old = Assignment::factory()->create([
        'equipment_id' => $equipment->id,
        'service_id' => $this->service->id,
        'start_date' => '2025-01-01',
        'end_date' => '2025-12-31',
    ]);
    $current = Assignment::factory()->create([
        'equipment_id' => $equipment->id,
        'service_id' => Service::factory()->create(['name' => 'Deanery Office'])->id,
        'start_date' => '2026-01-01',
    ]);

    $this->actingAs(actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE));

    Livewire::test(AssignmentsRelationManager::class, [
        'ownerRecord' => $equipment,
        'pageClass' => ViewEquipment::class,
    ])->assertCanSeeTableRecords([$old, $current]);

    // The infolist surfaces the current assignment on the view page itself.
    $this->get(EquipmentResource::getUrl('view', ['record' => $equipment]))
        ->assertOk()
        ->assertSee('Deanery Office');
});
