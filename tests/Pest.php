<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Create a user holding the given role; elevated roles get a fake MFA secret
 * so Filament's required-MFA middleware lets them into the panel.
 */
function actingUserWithRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    if ($user->requiresMultiFactorAuthentication()) {
        $user->forceFill(['app_authentication_secret' => 'TESTSECRETTESTSECRET'])->save();
    }

    return $user;
}
