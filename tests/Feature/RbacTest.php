<?php

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\RoleName;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('seeds exactly the locked role set', function (): void {
    $roles = Role::query()->pluck('name')->sort()->values()->all();

    expect($roles)->toEqualCanonicalizing(RoleName::all());
});

it('lets every role in the matrix reach the panel dashboard', function (string $role): void {
    $user = actingUserWithRole($role);

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk();
})->with(RoleName::all());

it('denies tout_utilisateur access to user management', function (): void {
    $user = actingUserWithRole(RoleName::TOUT_UTILISATEUR);

    $this->actingAs($user)
        ->get(UserResource::getUrl('index'))
        ->assertForbidden();
});

it('denies enseignant access to user management', function (): void {
    $user = actingUserWithRole(RoleName::ENSEIGNANT);

    $this->actingAs($user)
        ->get(UserResource::getUrl('index'))
        ->assertForbidden();
});

it('allows super_admin to reach user management', function (): void {
    $user = actingUserWithRole(RoleName::SUPER_ADMIN);

    $this->actingAs($user)
        ->get(UserResource::getUrl('index'))
        ->assertOk();
});

it('redirects an elevated-role user without MFA to the MFA set-up flow', function (): void {
    $user = User::factory()->create();
    $user->assignRole(RoleName::GESTIONNAIRE_PATRIMOINE);

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect();
});

it('does not force MFA on non-elevated roles', function (): void {
    $user = User::factory()->create();
    $user->assignRole(RoleName::TOUT_UTILISATEUR);

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk();
});
