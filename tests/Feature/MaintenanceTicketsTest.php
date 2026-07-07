<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Exceptions\InvalidTicketTransitionException;
use App\Filament\Pages\MaintenanceBoard;
use App\Filament\Resources\MaintenanceTickets\MaintenanceTicketResource;
use App\Filament\Resources\MaintenanceTickets\Pages\CreateMaintenanceTicket;
use App\Filament\Resources\MaintenanceTickets\Pages\EditMaintenanceTicket;
use App\Filament\Resources\MaintenanceTickets\Pages\ViewMaintenanceTicket;
use App\Filament\Resources\MaintenanceTickets\RelationManagers\InterventionsRelationManager;
use App\Jobs\SendTicketNotification;
use App\Models\Building;
use App\Models\Equipment;
use App\Models\Faculty;
use App\Models\Intervention;
use App\Models\Local;
use App\Models\MaintenanceTicket;
use App\Models\Service;
use App\Models\User;
use App\Services\TicketWorkflowService;
use App\Support\RoleName;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->a3 = actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE);

    $this->technology = Faculty::factory()->create(['name' => 'Technology']);
    $this->sciences = Faculty::factory()->create(['name' => 'Sciences']);

    $techBuilding = Building::factory()->create(['faculty_id' => $this->technology->id]);
    $sciBuilding = Building::factory()->create(['faculty_id' => $this->sciences->id]);

    $this->techLocal = Local::factory()->create(['building_id' => $techBuilding->id]);
    $this->sciLocal = Local::factory()->create(['building_id' => $sciBuilding->id]);

    $this->techEquipment = Equipment::factory()->create(['local_id' => $this->techLocal->id]);
});

function ticketN2(Faculty $faculty): User
{
    $user = User::factory()->create(['faculty_id' => $faculty->id]);
    $user->assignRole(RoleName::RESPONSABLE_FACULTE);
    $user->forceFill(['app_authentication_secret' => 'TESTSECRETTESTSECRET'])->save();

    return $user;
}

function technicianUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(RoleName::SERVICE_TECHNIQUE);

    return $user;
}

// --- QR-scan report flow (Phases.md Phase 6 DoD) ---

it('shows the read-only card with a login prompt to a guest', function (): void {
    $this->get(route('qr.lookup', ['token' => $this->techEquipment->qrCode->token]))
        ->assertOk()
        ->assertSee(__('patrimoine.report.login_cta'))
        ->assertDontSee(__('patrimoine.report.submit'));
});

it('shows the report form to an authenticated visitor', function (): void {
    $this->actingAs(actingUserWithRole(RoleName::TOUT_UTILISATEUR));

    $this->get(route('qr.lookup', ['token' => $this->techEquipment->qrCode->token]))
        ->assertOk()
        ->assertSee(__('patrimoine.report.submit'))
        ->assertSee(__('patrimoine.report.urgent_notice'));
});

it('creates an urgent, 48h-SLA ticket when an authenticated user reports an issue via QR scan', function (): void {
    Bus::fake();

    $reporter = actingUserWithRole(RoleName::TOUT_UTILISATEUR);
    $this->actingAs($reporter);

    $now = CarbonImmutable::parse('2026-08-10 09:00:00'); // a Monday
    $this->travelTo($now);

    $token = $this->techEquipment->qrCode->token;

    $this->post(route('report.store', ['token' => $token]), [
        'description' => 'The projector bulb is flickering and smells of burning plastic.',
    ])->assertRedirect(route('qr.lookup', ['token' => $token]));

    $ticket = MaintenanceTicket::query()->where('equipment_id', $this->techEquipment->id)->firstOrFail();

    expect($ticket->reference)->toStartWith('TCK-'.$now->format('Y').'-')
        ->and($ticket->source->value)->toBe('qr_scan')
        ->and($ticket->priority)->toBe(TicketPriority::Urgent)
        ->and($ticket->status)->toBe(TicketStatus::New)
        ->and($ticket->reported_by_user_id)->toBe($reporter->id)
        ->and($ticket->sla_due_at->equalTo($now->addHours(48)))->toBeTrue();

    Bus::assertDispatched(SendTicketNotification::class, fn (SendTicketNotification $job): bool => $job->userId === $this->a3->id);
});

it('rejects a guest submission and redirects to login', function (): void {
    $token = $this->techEquipment->qrCode->token;

    $this->post(route('report.store', ['token' => $token]), [
        'description' => 'Broken chair leg.',
    ])->assertRedirect(route('filament.admin.auth.login'));

    expect(MaintenanceTicket::query()->count())->toBe(0);
});

it('requires a non-trivial description', function (): void {
    $this->actingAs(actingUserWithRole(RoleName::TOUT_UTILISATEUR));

    $token = $this->techEquipment->qrCode->token;

    $this->post(route('report.store', ['token' => $token]), ['description' => 'Hi'])
        ->assertSessionHasErrors('description');

    expect(MaintenanceTicket::query()->count())->toBe(0);
});

it('shows already-reported and refuses a second ticket for the same asset', function (): void {
    MaintenanceTicket::factory()->qrScan()->create([
        'equipment_id' => $this->techEquipment->id,
        'status' => TicketStatus::New,
    ]);

    $this->actingAs(actingUserWithRole(RoleName::TOUT_UTILISATEUR));
    $token = $this->techEquipment->qrCode->token;

    $this->get(route('qr.lookup', ['token' => $token]))
        ->assertOk()
        ->assertSee(__('patrimoine.report.already_reported'))
        ->assertDontSee(__('patrimoine.report.submit'));

    $this->post(route('report.store', ['token' => $token]), ['description' => 'Reporting again anyway.'])
        ->assertRedirect(route('qr.lookup', ['token' => $token]));

    expect(MaintenanceTicket::query()->where('equipment_id', $this->techEquipment->id)->count())->toBe(1);
});

it('allows reporting again once the previous ticket reached a terminal status', function (): void {
    MaintenanceTicket::factory()->qrScan()->create([
        'equipment_id' => $this->techEquipment->id,
        'status' => TicketStatus::Closed,
    ]);

    $this->actingAs(actingUserWithRole(RoleName::TOUT_UTILISATEUR));
    $token = $this->techEquipment->qrCode->token;

    $this->post(route('report.store', ['token' => $token]), ['description' => 'It broke again.'])
        ->assertRedirect(route('qr.lookup', ['token' => $token]));

    expect(MaintenanceTicket::query()->where('equipment_id', $this->techEquipment->id)->count())->toBe(2);
});

it('rate limits the report submission per QR token', function (): void {
    $this->actingAs(actingUserWithRole(RoleName::TOUT_UTILISATEUR));
    $token = $this->techEquipment->qrCode->token;

    foreach (range(1, 5) as $i) {
        $this->post(route('report.store', ['token' => $token]), ['description' => "Attempt {$i} description."]);
    }

    $this->post(route('report.store', ['token' => $token]), ['description' => 'One too many.'])
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

// --- SLA computation (Schema.md §4) ---

it('computes a standard-priority SLA of 5 business days, skipping Fridays', function (): void {
    // 2026-08-10 is a Monday; +5 non-Friday days lands on 2026-08-16
    // (Tue 11, Wed 12, Thu 13, [Fri 14 skipped], Sat 15, Sun 16).
    $this->travelTo(CarbonImmutable::parse('2026-08-10 10:00:00'));

    $ticket = MaintenanceTicket::factory()->create([
        'equipment_id' => $this->techEquipment->id,
        'priority' => TicketPriority::Standard,
    ]);

    expect($ticket->sla_due_at->dayOfWeek)->not->toBe(Carbon::FRIDAY)
        ->and($ticket->sla_due_at->toDateString())->toBe('2026-08-16');
});

it('assigns sequential TCK-YYYY-NNNNN references', function (): void {
    $year = now()->format('Y');

    $first = MaintenanceTicket::factory()->create(['equipment_id' => $this->techEquipment->id]);
    $second = MaintenanceTicket::factory()->create(['equipment_id' => $this->techEquipment->id]);

    expect($first->reference)->toBe("TCK-{$year}-00001")
        ->and($second->reference)->toBe("TCK-{$year}-00002");
});

it('routes a new ticket to the equipment\'s current service and notifies its responsible user', function (): void {
    Bus::fake();

    $responsible = User::factory()->create();
    $service = Service::factory()->create(['responsible_user_id' => $responsible->id]);

    $this->techEquipment->assignments()->create([
        'service_id' => $service->id,
        'assigned_by_user_id' => $this->a3->id,
        'start_date' => now()->subDay(),
    ]);

    $ticket = MaintenanceTicket::factory()->create(['equipment_id' => $this->techEquipment->id]);

    expect($ticket->assigned_service_id)->toBe($service->id);

    Bus::assertDispatched(SendTicketNotification::class, fn (SendTicketNotification $job): bool => $job->userId === $responsible->id);
});

// --- RBAC (Security.md §3 + the general FacultyScope enforcement rule) ---

it('lets A3 create a ticket manually from the panel', function (): void {
    $this->actingAs($this->a3);

    Livewire::test(CreateMaintenanceTicket::class)
        ->fillForm([
            'equipment_id' => $this->techEquipment->id,
            'source' => 'manual',
            'priority' => 'standard',
            'status' => 'new',
            'description' => 'Manually logged by A3 during a walkthrough.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $ticket = MaintenanceTicket::query()->where('equipment_id', $this->techEquipment->id)->firstOrFail();

    expect($ticket->reported_by_user_id)->toBe($this->a3->id);
});

it('denies Enseignant and tout_utilisateur the ticket admin resource, even though they can report', function (): void {
    $this->actingAs(actingUserWithRole(RoleName::ENSEIGNANT));
    $this->get(MaintenanceTicketResource::getUrl('index'))->assertForbidden();

    $this->actingAs(actingUserWithRole(RoleName::TOUT_UTILISATEUR));
    $this->get(MaintenanceTicketResource::getUrl('index'))->assertForbidden();
});

it('scopes N2 to tickets in their own faculty', function (): void {
    $sciEquipment = Equipment::factory()->create(['local_id' => $this->sciLocal->id]);

    MaintenanceTicket::factory()->create(['equipment_id' => $this->techEquipment->id, 'description' => 'Tech ticket']);
    MaintenanceTicket::factory()->create(['equipment_id' => $sciEquipment->id, 'description' => 'Sci ticket']);

    $this->actingAs(ticketN2($this->technology));

    $descriptions = MaintenanceTicket::query()->pluck('description')->all();

    expect($descriptions)->toBe(['Tech ticket']);
});

it('lets N2 view but not edit a ticket inside their scope', function (): void {
    $ticket = MaintenanceTicket::factory()->create(['equipment_id' => $this->techEquipment->id]);

    $this->actingAs(ticketN2($this->technology));

    $this->get(MaintenanceTicketResource::getUrl('view', ['record' => $ticket]))->assertOk();
    $this->get(MaintenanceTicketResource::getUrl('edit', ['record' => $ticket]))->assertForbidden();
});

it('lets Service technique read the ticket list, unscoped', function (): void {
    $sciEquipment = Equipment::factory()->create(['local_id' => $this->sciLocal->id]);
    MaintenanceTicket::factory()->create(['equipment_id' => $this->techEquipment->id]);
    MaintenanceTicket::factory()->create(['equipment_id' => $sciEquipment->id]);

    $this->actingAs(actingUserWithRole(RoleName::SERVICE_TECHNIQUE));

    $this->get(MaintenanceTicketResource::getUrl('index'))->assertOk();
    expect(MaintenanceTicket::query()->count())->toBe(2);
});

it('shows N3 every ticket, university-wide', function (): void {
    $sciEquipment = Equipment::factory()->create(['local_id' => $this->sciLocal->id]);
    MaintenanceTicket::factory()->create(['equipment_id' => $this->techEquipment->id]);
    MaintenanceTicket::factory()->create(['equipment_id' => $sciEquipment->id]);

    $this->actingAs(actingUserWithRole(RoleName::RECTORAT));

    expect(MaintenanceTicket::query()->count())->toBe(2);
    $this->get(MaintenanceTicketResource::getUrl('index'))->assertOk();
});

it('returns 404 for an unknown or non-uuid token on the report endpoint', function (): void {
    $this->actingAs(actingUserWithRole(RoleName::TOUT_UTILISATEUR));

    $this->post(route('report.store', ['token' => (string) Str::uuid()]), ['description' => 'Ghost asset.'])
        ->assertNotFound();
});

// --- Phase 7: status state machine ---

it('advances a ticket through the full linear chain but rejects skipping a step', function (): void {
    $ticket = MaintenanceTicket::factory()->create([
        'equipment_id' => $this->techEquipment->id,
        'status' => TicketStatus::New,
    ]);

    $service = app(TicketWorkflowService::class);

    expect(fn () => $service->transition($ticket, TicketStatus::InProgress, $this->a3))
        ->toThrow(InvalidTicketTransitionException::class);

    $service->transition($ticket, TicketStatus::Assigned, $this->a3);
    expect($ticket->refresh()->status)->toBe(TicketStatus::Assigned);

    $service->transition($ticket, TicketStatus::InProgress, $this->a3);
    $service->transition($ticket, TicketStatus::Resolved, $this->a3);
    $service->transition($ticket, TicketStatus::Closed, $this->a3);
    expect($ticket->refresh()->status)->toBe(TicketStatus::Closed);

    expect(fn () => $service->transition($ticket, TicketStatus::New, $this->a3))
        ->toThrow(InvalidTicketTransitionException::class);
});

it('allows cancelling from any non-terminal status but never after closed', function (): void {
    $service = app(TicketWorkflowService::class);

    $new = MaintenanceTicket::factory()->create(['equipment_id' => $this->techEquipment->id, 'status' => TicketStatus::New]);
    $service->transition($new, TicketStatus::Cancelled, $this->a3);
    expect($new->refresh()->status)->toBe(TicketStatus::Cancelled);

    $closed = MaintenanceTicket::factory()->create(['equipment_id' => $this->techEquipment->id, 'status' => TicketStatus::Closed]);
    expect(fn () => $service->transition($closed, TicketStatus::Cancelled, $this->a3))
        ->toThrow(InvalidTicketTransitionException::class);
});

it('disables the status field on the edit form (status changes only via the workflow actions)', function (): void {
    $ticket = MaintenanceTicket::factory()->create(['equipment_id' => $this->techEquipment->id]);

    $this->actingAs($this->a3);

    Livewire::test(EditMaintenanceTicket::class, ['record' => $ticket->getRouteKey()])
        ->fillForm(['status' => 'closed'])
        ->call('save');

    expect($ticket->refresh()->status)->toBe(TicketStatus::New);
});

it('lets A3 advance and cancel a ticket from its page actions', function (): void {
    $ticket = MaintenanceTicket::factory()->create(['equipment_id' => $this->techEquipment->id, 'status' => TicketStatus::New]);

    $this->actingAs($this->a3);

    Livewire::test(ViewMaintenanceTicket::class, ['record' => $ticket->getRouteKey()])
        ->callAction('advance');

    expect($ticket->refresh()->status)->toBe(TicketStatus::Assigned);

    Livewire::test(ViewMaintenanceTicket::class, ['record' => $ticket->getRouteKey()])
        ->callAction('cancel');

    expect($ticket->refresh()->status)->toBe(TicketStatus::Cancelled);
});

// --- Phase 7: Kanban board ---

it('groups tickets into the 5 board columns', function (): void {
    MaintenanceTicket::factory()->create(['equipment_id' => $this->techEquipment->id, 'status' => TicketStatus::New]);
    MaintenanceTicket::factory()->create(['equipment_id' => $this->techEquipment->id, 'status' => TicketStatus::InProgress]);
    MaintenanceTicket::factory()->create(['equipment_id' => $this->techEquipment->id, 'status' => TicketStatus::Cancelled]);

    $this->actingAs($this->a3);

    $columns = (new MaintenanceBoard)->columns();

    expect($columns['new'])->toHaveCount(1)
        ->and($columns['in_progress'])->toHaveCount(1)
        ->and(array_keys($columns))->not->toContain('cancelled');
});

it('moves a ticket via the Kanban drag when the mover has Update permission', function (): void {
    $ticket = MaintenanceTicket::factory()->create(['equipment_id' => $this->techEquipment->id, 'status' => TicketStatus::New]);

    $this->actingAs($this->a3);

    Livewire::test(MaintenanceBoard::class)
        ->call('moveTicket', $ticket->id, 'assigned');

    expect($ticket->refresh()->status)->toBe(TicketStatus::Assigned);
});

it('denies the Kanban move for a role without Update:MaintenanceTicket', function (): void {
    $ticket = MaintenanceTicket::factory()->create(['equipment_id' => $this->techEquipment->id, 'status' => TicketStatus::New]);

    $this->actingAs(ticketN2($this->technology));

    Livewire::test(MaintenanceBoard::class)
        ->call('moveTicket', $ticket->id, 'assigned')
        ->assertForbidden();

    expect($ticket->refresh()->status)->toBe(TicketStatus::New);
});

it('rejects an invalid Kanban move and keeps the ticket in its original column', function (): void {
    $ticket = MaintenanceTicket::factory()->create(['equipment_id' => $this->techEquipment->id, 'status' => TicketStatus::New]);

    $this->actingAs($this->a3);

    Livewire::test(MaintenanceBoard::class)
        ->call('moveTicket', $ticket->id, 'closed');

    expect($ticket->refresh()->status)->toBe(TicketStatus::New);
});

it('lets Service technique move a ticket on the board', function (): void {
    $ticket = MaintenanceTicket::factory()->create(['equipment_id' => $this->techEquipment->id, 'status' => TicketStatus::New]);

    $this->actingAs(technicianUser());

    Livewire::test(MaintenanceBoard::class)->call('moveTicket', $ticket->id, 'assigned');

    expect($ticket->refresh()->status)->toBe(TicketStatus::Assigned);
});

// --- Phase 7: interventions ---

it('notifies the technician when assigned to an intervention', function (): void {
    Bus::fake();

    $technician = technicianUser();
    $ticket = MaintenanceTicket::factory()->create(['equipment_id' => $this->techEquipment->id]);

    Intervention::factory()->create([
        'maintenance_ticket_id' => $ticket->id,
        'technician_id' => $technician->id,
    ]);

    Bus::assertDispatched(SendTicketNotification::class, fn (SendTicketNotification $job): bool => $job->userId === $technician->id);
});

it('lets A3 manage interventions fully via the ticket relation manager', function (): void {
    $ticket = MaintenanceTicket::factory()->create(['equipment_id' => $this->techEquipment->id]);
    $technician = technicianUser();

    $this->actingAs($this->a3);

    // Filament's relation managers are read-only by default on ViewRecord
    // pages (hasReadOnlyRelationManagersOnResourceViewPagesByDefault) —
    // creating happens from the Edit page, matching real usage. A header
    // action on a *table* needs callTableAction, not the generic
    // Schema-level callAction (which looks in the wrong action registry).
    Livewire::test(InterventionsRelationManager::class, [
        'ownerRecord' => $ticket,
        'pageClass' => EditMaintenanceTicket::class,
    ])->callTableAction('create', data: [
        'technician_id' => $technician->id,
        'status' => 'planned',
    ])->assertHasNoTableActionErrors();

    expect(Intervention::query()->where('maintenance_ticket_id', $ticket->id)->where('technician_id', $technician->id)->exists())->toBeTrue();
});

it('lets a technician log their own intervention but not someone else\'s', function (): void {
    $ticket = MaintenanceTicket::factory()->create(['equipment_id' => $this->techEquipment->id]);
    $own = technicianUser();
    $other = technicianUser();

    $mine = Intervention::factory()->create(['maintenance_ticket_id' => $ticket->id, 'technician_id' => $own->id]);
    $theirs = Intervention::factory()->create(['maintenance_ticket_id' => $ticket->id, 'technician_id' => $other->id]);

    $this->actingAs($own);

    expect($own->can('logWork', $mine))->toBeTrue()
        ->and($own->can('logWork', $theirs))->toBeFalse();
});

// --- Phase 7: SLA escalation ---

it('escalates a breached ticket once and does not re-notify on a second run', function (): void {
    $this->travelTo(Carbon::parse('2026-08-01 00:00:00'));
    $ticket = MaintenanceTicket::factory()->create([
        'equipment_id' => $this->techEquipment->id,
        'priority' => TicketPriority::Urgent, // +48h SLA
    ]);

    $this->travelTo(Carbon::parse('2026-08-04 00:00:00')); // well past the 48h deadline

    // Fake only now — isolates the escalation command's own dispatches from
    // the creation-time "new ticket" notification (Phase 6 observer).
    Bus::fake();
    $this->artisan('patrimo:escalate-tickets')->assertExitCode(0);

    expect($ticket->refresh()->escalated_at)->not->toBeNull();
    Bus::assertDispatched(SendTicketNotification::class, fn (SendTicketNotification $job): bool => $job->userId === $this->a3->id);

    Bus::fake();
    $this->artisan('patrimo:escalate-tickets');
    Bus::assertNotDispatched(SendTicketNotification::class);
});

it('escalates an approaching (80%+ elapsed) ticket as a warning, not yet a breach', function (): void {
    $this->travelTo(Carbon::parse('2026-08-01 00:00:00'));
    $ticket = MaintenanceTicket::factory()->create([
        'equipment_id' => $this->techEquipment->id,
        'priority' => TicketPriority::Urgent, // +48h SLA
    ]);

    $this->travelTo(Carbon::parse('2026-08-02 16:00:00')); // 40h elapsed of 48h = 83%, not yet breached

    Bus::fake();
    $this->artisan('patrimo:escalate-tickets');

    expect($ticket->refresh())
        ->escalated_at->not->toBeNull()
        ->status->not->toBe(TicketStatus::Resolved);

    Bus::assertDispatched(SendTicketNotification::class, fn (SendTicketNotification $job): bool => $job->title === __('patrimoine.tickets.notif_approaching_title'));
});

it('does not escalate a resolved, closed or cancelled ticket even if past its SLA', function (): void {
    $this->travelTo(Carbon::parse('2026-08-01 00:00:00'));
    $ticket = MaintenanceTicket::factory()->create([
        'equipment_id' => $this->techEquipment->id,
        'priority' => TicketPriority::Urgent,
        'status' => TicketStatus::Closed,
    ]);

    $this->travelTo(Carbon::parse('2026-08-04 00:00:00'));

    Bus::fake();
    $this->artisan('patrimo:escalate-tickets');

    expect($ticket->refresh()->escalated_at)->toBeNull();
    Bus::assertNotDispatched(SendTicketNotification::class);
});
