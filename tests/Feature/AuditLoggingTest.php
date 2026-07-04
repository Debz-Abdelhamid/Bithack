<?php

use App\Models\Role;
use App\Models\User;
use App\Support\RoleName;
use Database\Seeders\RoleSeeder;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('logs failed login attempts with ip and email', function (): void {
    $user = User::factory()->create();

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'wrong-password'])
        ->call('authenticate');

    $entry = Activity::query()
        ->where('log_name', 'auth')
        ->where('description', 'login_failed')
        ->latest('id')
        ->first();

    expect($entry)->not->toBeNull()
        ->and($entry->properties['email'])->toBe($user->email);
});

it('logs successful logins', function (): void {
    $user = User::factory()->create(['password' => 'Motdepasse!Solide123']);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'Motdepasse!Solide123'])
        ->call('authenticate');

    expect(
        Activity::query()
            ->where('log_name', 'auth')
            ->where('description', 'login')
            ->where('causer_id', $user->id)
            ->exists()
    )->toBeTrue();
});

it('logs role assignments to the rbac audit trail', function (): void {
    $user = User::factory()->create();

    $user->assignRole(RoleName::ENSEIGNANT);

    $entry = Activity::query()
        ->where('log_name', 'rbac')
        ->where('description', 'roles_attached')
        ->where('subject_id', $user->id)
        ->first();

    expect($entry)->not->toBeNull();
});

it('logs role removals', function (): void {
    $user = User::factory()->create();
    $user->assignRole(RoleName::ENSEIGNANT);

    $user->removeRole(RoleName::ENSEIGNANT);

    expect(
        Activity::query()
            ->where('log_name', 'rbac')
            ->where('description', 'roles_detached')
            ->where('subject_id', $user->id)
            ->exists()
    )->toBeTrue();
});

it('logs permission grants to roles', function (): void {
    $permission = Permission::create(['name' => 'Test:Permission', 'guard_name' => 'web']);

    $role = Role::findByName(RoleName::GESTIONNAIRE_PATRIMOINE, 'web');
    $role->givePermissionTo($permission);

    expect(
        Activity::query()
            ->where('log_name', 'rbac')
            ->where('description', 'permissions_attached')
            ->exists()
    )->toBeTrue();
});
