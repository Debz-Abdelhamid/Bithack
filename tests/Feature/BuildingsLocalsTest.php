<?php

use App\Filament\Pages\CampusMap;
use App\Filament\Resources\Buildings\BuildingResource;
use App\Filament\Resources\Buildings\Pages\CreateBuilding;
use App\Filament\Resources\Locals\Pages\CreateLocal;
use App\Models\Building;
use App\Models\Faculty;
use App\Models\Local;
use App\Models\User;
use App\Support\RoleName;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->technology = Faculty::factory()->create(['name' => 'Technology']);
    $this->sciences = Faculty::factory()->create(['name' => 'Sciences']);

    $this->techBuilding = Building::factory()->create([
        'faculty_id' => $this->technology->id,
        'name' => 'Tech Hall',
    ]);
    $this->sciBuilding = Building::factory()->create([
        'faculty_id' => $this->sciences->id,
        'name' => 'Science Hall',
    ]);
    $this->sharedBuilding = Building::factory()->create([
        'faculty_id' => null,
        'name' => 'Shared Library',
    ]);

    Local::factory()->create(['building_id' => $this->techBuilding->id, 'name' => 'Tech Room']);
    Local::factory()->create(['building_id' => $this->sciBuilding->id, 'name' => 'Science Room']);
    Local::factory()->create(['building_id' => $this->sharedBuilding->id, 'name' => 'Shared Room']);
});

function n2ForFaculty(Faculty $faculty): User
{
    $user = User::factory()->create(['faculty_id' => $faculty->id]);
    $user->assignRole(RoleName::RESPONSABLE_FACULTE);
    $user->forceFill(['app_authentication_secret' => 'TESTSECRETTESTSECRET'])->save();

    return $user;
}

it('lets A3 create a building and a room inside it', function (): void {
    $this->actingAs(actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE));

    Livewire::test(CreateBuilding::class)
        ->fillForm([
            'code' => 'BAT-NEW',
            'name' => 'New Engineering Block',
            'faculty_id' => $this->technology->id,
            'status' => 'active',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $building = Building::query()->where('code', 'BAT-NEW')->firstOrFail();

    Livewire::test(CreateLocal::class)
        ->fillForm([
            'building_id' => $building->id,
            'code' => 'NEW-101',
            'name' => 'Lecture Hall 101',
            'type' => 'amphi',
            'capacity' => 200,
            'status' => 'available',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($building->locals()->where('code', 'NEW-101')->exists())->toBeTrue();
});

it('scopes N2 to their faculty plus shared buildings', function (): void {
    $this->actingAs(n2ForFaculty($this->technology));

    $names = Building::query()->pluck('name')->sort()->values()->all();

    expect($names)->toBe(['Shared Library', 'Tech Hall']);
});

it('scopes N2 room queries through their building faculty', function (): void {
    $this->actingAs(n2ForFaculty($this->technology));

    $names = Local::query()->pluck('name')->sort()->values()->all();

    expect($names)->toBe(['Shared Room', 'Tech Room']);
});

it("returns 404 when N2 opens another faculty's building", function (): void {
    $this->actingAs(n2ForFaculty($this->technology));

    $this->get(BuildingResource::getUrl('edit', ['record' => $this->sciBuilding]))
        ->assertNotFound();
});

it('denies N2 the building create page', function (): void {
    $this->actingAs(n2ForFaculty($this->technology));

    $this->get(BuildingResource::getUrl('create'))->assertForbidden();
});

it('does not scope A3 — full campus visibility', function (): void {
    $this->actingAs(actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE));

    expect(Building::query()->count())->toBe(3)
        ->and(Local::query()->count())->toBe(3);
});

it('denies tout_utilisateur the buildings resource but allows the campus map', function (): void {
    $this->actingAs(actingUserWithRole(RoleName::TOUT_UTILISATEUR));

    $this->get(BuildingResource::getUrl('index'))->assertForbidden();
    $this->get(CampusMap::getUrl())->assertOk();
});

it('shows the full campus to faculty-bound teachers on the map', function (): void {
    $teacher = User::factory()->create(['faculty_id' => $this->sciences->id]);
    $teacher->assignRole(RoleName::ENSEIGNANT);

    $this->actingAs($teacher);

    $payload = Livewire::test(CampusMap::class)->instance()->getBuildingsPayload();

    expect($payload)->toHaveCount(3);
});

it('denies teachers the map picking action', function (): void {
    $teacher = User::factory()->create(['faculty_id' => $this->sciences->id]);
    $teacher->assignRole(RoleName::ENSEIGNANT);

    $this->actingAs($teacher);

    Livewire::test(CampusMap::class)
        ->call('setCoordinates', 36.9, 7.8)
        ->assertForbidden();
});

it('lets A3 reposition a building from the map — policy-checked', function (): void {
    $this->actingAs(actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE));

    Livewire::test(CampusMap::class)
        ->call('selectBuilding', $this->techBuilding->id)
        ->call('startPicking')
        ->call('setCoordinates', 36.9000001, 7.8000001);

    $this->techBuilding->refresh();

    expect(round($this->techBuilding->latitude, 5))->toBe(36.9)
        ->and(round($this->techBuilding->longitude, 5))->toBe(7.8);
});
