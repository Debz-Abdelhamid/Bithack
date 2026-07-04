<?php

use App\Models\User;
use App\Support\RoleName;
use Database\Seeders\RoleSeeder;
use Filament\Auth\Pages\Login;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('locks the login form after repeated failed attempts', function (): void {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    foreach (range(1, 5) as $attempt) {
        Livewire::test(Login::class)
            ->fillForm([
                'email' => $user->email,
                'password' => 'wrong-password-'.$attempt,
            ])
            ->call('authenticate');
    }

    // Even the correct password is rejected while the limiter is active.
    Livewire::test(Login::class)
        ->fillForm([
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ])
        ->call('authenticate');

    expect(auth()->check())->toBeFalse();
});

it('ends an elevated-role session after the idle timeout', function (): void {
    $user = actingUserWithRole(RoleName::GESTIONNAIRE_PATRIMOINE);

    $this->actingAs($user)->get('/admin')->assertOk();

    // Simulate a stale session: last activity far beyond the timeout.
    session()->put(
        'patrimo_elevated_last_activity',
        now()->subMinutes(config('patrimo.security.elevated_idle_timeout_minutes') + 10)->getTimestamp(),
    );

    $this->get('/admin')->assertRedirect();
    $this->assertGuest();
});

it('keeps a non-elevated session alive past the elevated timeout', function (): void {
    $user = actingUserWithRole(RoleName::TOUT_UTILISATEUR);

    $this->actingAs($user)->get('/admin')->assertOk();

    session()->put(
        'patrimo_elevated_last_activity',
        now()->subMinutes(config('patrimo.security.elevated_idle_timeout_minutes') + 10)->getTimestamp(),
    );

    $this->get('/admin')->assertOk();
});
