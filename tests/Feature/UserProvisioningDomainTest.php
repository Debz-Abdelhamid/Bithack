<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleName;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    config()->set('patrimo.registration.allowed_domains', ['univ-annaba.dz']);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(actingUserWithRole(RoleName::SUPER_ADMIN));
});

it('blocks provisioning a user with a non-institutional email', function (): void {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Externe Bloqué',
            'email' => 'externe@gmail.com',
            'password' => 'Motdepasse!Solide123',
        ])
        ->call('create')
        ->assertHasFormErrors(['email']);

    expect(User::query()->where('email', 'externe@gmail.com')->exists())->toBeFalse();
});

it('provisions a user with an institutional email', function (): void {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Nouveau Membre',
            'email' => 'nouveau.membre@univ-annaba.dz',
            'password' => 'Motdepasse!Solide123',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'nouveau.membre@univ-annaba.dz')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeTrue(); // provisioned = pre-verified
});

it('still lets admins edit a legacy out-of-domain account without changing its email', function (): void {
    $legacy = User::factory()->create(['email' => 'demo@demo.ubma.dz', 'name' => 'Avant']);

    Livewire::test(EditUser::class, ['record' => $legacy->getRouteKey()])
        ->fillForm(['name' => 'Après Modification'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($legacy->refresh()->name)->toBe('Après Modification');
});

it('requires a faculty when assigning the N2 role', function (): void {
    $n2RoleId = Role::query()
        ->where('name', RoleName::RESPONSABLE_FACULTE)
        ->value('id');

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Faculty Head Without Faculty',
            'email' => 'headless@univ-annaba.dz',
            'password' => 'Motdepasse!Solide123',
            'roles' => [$n2RoleId],
        ])
        ->call('create')
        ->assertHasFormErrors(['faculty_id']);

    expect(User::query()->where('email', 'headless@univ-annaba.dz')->exists())->toBeFalse();
});

it('does not require a faculty for teachers — affiliation only', function (): void {
    $teacherRoleId = Role::query()
        ->where('name', RoleName::ENSEIGNANT)
        ->value('id');

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Campus-Wide Teacher',
            'email' => 'teacher.nofaculty@univ-annaba.dz',
            'password' => 'Motdepasse!Solide123',
            'roles' => [$teacherRoleId],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('email', 'teacher.nofaculty@univ-annaba.dz')->exists())->toBeTrue();
});

it('blocks changing an existing email to a non-institutional one', function (): void {
    $legacy = User::factory()->create(['email' => 'demo@demo.ubma.dz']);

    Livewire::test(EditUser::class, ['record' => $legacy->getRouteKey()])
        ->fillForm(['email' => 'fuite@hotmail.com'])
        ->call('save')
        ->assertHasFormErrors(['email']);

    expect($legacy->refresh()->email)->toBe('demo@demo.ubma.dz');
});
