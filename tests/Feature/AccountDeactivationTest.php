<?php

use App\Support\RoleName;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('locks a deactivated account out of the panel immediately', function (): void {
    $user = actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE);

    $this->actingAs($user)->get('/admin')->assertOk();

    $user->forceFill(['is_active' => false])->save();

    $this->get('/admin')->assertForbidden();
});

it('keeps active accounts unaffected', function (): void {
    $user = actingUserWithRole(RoleName::TOUT_UTILISATEUR);

    expect($user->is_active)->toBeTrue();

    $this->actingAs($user)->get('/admin')->assertOk();
});
