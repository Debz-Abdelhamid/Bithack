<?php

use App\Filament\Resources\Equipments\EquipmentResource;
use App\Filament\Resources\Equipments\Pages\CreateEquipment;
use App\Filament\Resources\PurchaseReferences\Pages\ManagePurchaseReferences;
use App\Filament\Resources\PurchaseReferences\PurchaseReferenceResource;
use App\Models\Building;
use App\Models\Equipment;
use App\Models\Faculty;
use App\Models\Local;
use App\Models\PurchaseReference;
use App\Models\QrCode;
use App\Models\User;
use App\Support\RoleName;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->technology = Faculty::factory()->create(['name' => 'Technology']);
    $this->sciences = Faculty::factory()->create(['name' => 'Sciences']);

    $techBuilding = Building::factory()->create(['faculty_id' => $this->technology->id]);
    $sciBuilding = Building::factory()->create(['faculty_id' => $this->sciences->id]);
    $sharedBuilding = Building::factory()->create(['faculty_id' => null]);

    $this->techLocal = Local::factory()->create(['building_id' => $techBuilding->id]);
    $this->sciLocal = Local::factory()->create(['building_id' => $sciBuilding->id]);
    $this->sharedLocal = Local::factory()->create(['building_id' => $sharedBuilding->id]);
});

function equipmentN2(Faculty $faculty): User
{
    $user = User::factory()->create(['faculty_id' => $faculty->id]);
    $user->assignRole(RoleName::RESPONSABLE_FACULTE);
    $user->forceFill(['app_authentication_secret' => 'TESTSECRETTESTSECRET'])->save();

    return $user;
}

// --- Étape 1: unique inventory code + QR token on creation (Phase 3 DoD) ---

it('generates a unique sequential inventory code and an opaque QR token on creation', function (): void {
    $year = now()->format('Y');

    $first = Equipment::factory()->create();
    $second = Equipment::factory()->create();

    expect($first->inventory_code)->toBe("UBMA-{$year}-00001")
        ->and($second->inventory_code)->toBe("UBMA-{$year}-00002")
        ->and($first->qrCode)->not->toBeNull()
        ->and($first->qrCode->token)->toBeUuid()
        ->and($second->qrCode->token)->toBeUuid()
        ->and($first->qrCode->token)->not->toBe($second->qrCode->token)
        ->and($first->qrCode->printed)->toBeFalse();
});

it('continues the sequence after a manually entered code', function (): void {
    $year = now()->format('Y');

    Equipment::factory()->create(['inventory_code' => "UBMA-{$year}-00007"]);

    $auto = Equipment::factory()->create();

    expect($auto->inventory_code)->toBe("UBMA-{$year}-00008");
});

it('keeps a manually entered inventory code untouched', function (): void {
    $equipment = Equipment::factory()->create(['inventory_code' => 'LEGACY-1984-123']);

    expect($equipment->inventory_code)->toBe('LEGACY-1984-123')
        ->and($equipment->qrCode)->not->toBeNull();
});

it('removes the QR row when the equipment is deleted', function (): void {
    $equipment = Equipment::factory()->create();
    $qrId = $equipment->qrCode->id;

    $equipment->delete();

    expect(QrCode::query()->find($qrId))->toBeNull();
});

// --- RBAC: A3 full CRUD, N2 read-only + faculty scope, others denied ---

it('lets A3 create an equipment from the panel form', function (): void {
    $this->actingAs(actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE));

    Livewire::test(CreateEquipment::class)
        ->fillForm([
            'designation' => 'Oscilloscope',
            'category' => 'informatique',
            'local_id' => $this->techLocal->id,
            'status' => 'in_service',
            'condition' => 'new',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $equipment = Equipment::query()->where('designation', 'Oscilloscope')->firstOrFail();

    expect($equipment->inventory_code)->toStartWith('UBMA-')
        ->and($equipment->qrCode)->not->toBeNull();
});

it('denies N2 the equipment create page', function (): void {
    $this->actingAs(equipmentN2($this->technology));

    $this->get(EquipmentResource::getUrl('create'))->assertForbidden();
});

it('denies tout_utilisateur the equipments resource entirely', function (): void {
    $this->actingAs(actingUserWithRole(RoleName::TOUT_UTILISATEUR));

    $this->get(EquipmentResource::getUrl('index'))->assertForbidden();
});

it('scopes N2 to equipment in their faculty, shared buildings and central stock', function (): void {
    Equipment::factory()->create(['designation' => 'Tech asset', 'local_id' => $this->techLocal->id]);
    Equipment::factory()->create(['designation' => 'Science asset', 'local_id' => $this->sciLocal->id]);
    Equipment::factory()->create(['designation' => 'Shared asset', 'local_id' => $this->sharedLocal->id]);
    Equipment::factory()->create(['designation' => 'Unplaced asset', 'local_id' => null]);

    $this->actingAs(equipmentN2($this->technology));

    $names = Equipment::query()->pluck('designation')->sort()->values()->all();

    expect($names)->toBe(['Shared asset', 'Tech asset', 'Unplaced asset']);
});

it("returns 404 when N2 opens another faculty's equipment", function (): void {
    $foreign = Equipment::factory()->create(['local_id' => $this->sciLocal->id]);

    $this->actingAs(equipmentN2($this->technology));

    $this->get(EquipmentResource::getUrl('view', ['record' => $foreign]))->assertNotFound();
});

it('lets N2 view an equipment inside their scope, read-only', function (): void {
    $own = Equipment::factory()->create(['local_id' => $this->techLocal->id]);

    $this->actingAs(equipmentN2($this->technology));

    $this->get(EquipmentResource::getUrl('view', ['record' => $own]))->assertOk();
    $this->get(EquipmentResource::getUrl('edit', ['record' => $own]))->assertForbidden();
});

it('renders the equipment view page with its QR block for A3', function (): void {
    $equipment = Equipment::factory()->create();

    $this->actingAs(actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE));

    $this->get(EquipmentResource::getUrl('view', ['record' => $equipment]))
        ->assertOk()
        ->assertSee($equipment->inventory_code)
        ->assertSee($equipment->qrCode->token);
});

// --- Public QR lookup (Phase 3 DoD: public, rate-limited, read-only) ---

it('resolves a printed QR token to the public read-only lookup page', function (): void {
    $equipment = Equipment::factory()->create([
        'designation' => 'Video projector',
        'local_id' => $this->techLocal->id,
    ]);

    $this->get(route('qr.lookup', ['token' => $equipment->qrCode->token]))
        ->assertOk()
        ->assertSee('Video projector')
        ->assertSee($equipment->inventory_code);
});

it('never exposes value, serial, notes or photo on the public lookup', function (): void {
    $equipment = Equipment::factory()->create([
        'serial_number' => 'SECRET-SN-99881',
        'acquisition_value' => 123456.78,
        'notes' => 'confidential purchase context',
        'photo_path' => 'equipment-photos/secret.jpg',
    ]);

    $this->get(route('qr.lookup', ['token' => $equipment->qrCode->token]))
        ->assertOk()
        ->assertDontSee('SECRET-SN-99881')
        ->assertDontSee('123456.78')
        ->assertDontSee('confidential purchase context')
        ->assertDontSee('equipment-photos/secret.jpg');
});

it('returns 404 for an unknown token and for non-uuid junk', function (): void {
    $this->get(route('qr.lookup', ['token' => (string) Str::uuid()]))->assertNotFound();
    $this->get('/report/UBMA-2026-00001')->assertNotFound();
});

it('rate limits the public lookup per token', function (): void {
    $equipment = Equipment::factory()->create();
    $url = route('qr.lookup', ['token' => $equipment->qrCode->token]);

    foreach (range(1, 10) as $i) {
        $this->get($url)->assertOk();
    }

    $this->get($url)
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

// --- Print label flow ---

it('lets A3 print the label, marks the QR printed and audit-logs it', function (): void {
    $equipment = Equipment::factory()->create();

    $this->actingAs(actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE));

    $this->get(route('equipments.label', $equipment))
        ->assertOk()
        ->assertSee($equipment->inventory_code);

    expect($equipment->qrCode->refresh()->printed)->toBeTrue()
        ->and(
            Activity::query()
                ->where('description', 'label_printed')
                ->where('subject_type', Equipment::class)
                ->where('subject_id', $equipment->id)
                ->exists()
        )->toBeTrue();
});

it('denies N2 the print-label route even inside their scope', function (): void {
    $own = Equipment::factory()->create(['local_id' => $this->techLocal->id]);

    $this->actingAs(equipmentN2($this->technology));

    $this->get(route('equipments.label', $own))->assertForbidden();

    expect($own->qrCode->refresh()->printed)->toBeFalse();
});

it('redirects guests away from the print-label route', function (): void {
    $equipment = Equipment::factory()->create();

    $this->get(route('equipments.label', $equipment))->assertRedirect();
});

// --- Purchase reference stub (lien R7) ---

it('lets A3 create a purchase reference and link an equipment to it', function (): void {
    $this->actingAs(actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE));

    Livewire::test(ManagePurchaseReferences::class)
        ->callAction('create', [
            'external_purchase_id' => 'R7-2026-0099',
            'supplier' => 'Test Supplier',
        ])
        ->assertHasNoActionErrors();

    $reference = PurchaseReference::query()
        ->where('external_purchase_id', 'R7-2026-0099')
        ->firstOrFail();

    $equipment = Equipment::factory()->create(['purchase_reference_id' => $reference->id]);

    expect($equipment->purchaseReference->supplier)->toBe('Test Supplier');
});

it('denies tout_utilisateur the purchase references page', function (): void {
    $this->actingAs(actingUserWithRole(RoleName::TOUT_UTILISATEUR));

    $this->get(PurchaseReferenceResource::getUrl('index'))
        ->assertForbidden();
});
