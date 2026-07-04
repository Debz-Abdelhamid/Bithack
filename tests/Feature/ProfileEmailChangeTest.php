<?php

use App\Filament\Auth\EditProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('patrimo.registration.allowed_domains', ['univ-annaba.dz']);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('rejects changing the email to a non-institutional domain', function (): void {
    $user = User::factory()->create(['email' => 'membre@univ-annaba.dz']);

    Livewire::actingAs($user)
        ->test(EditProfile::class)
        ->fillForm(['email' => 'evasion@gmail.com'])
        ->call('save')
        ->assertHasFormErrors(['email']);

    expect($user->refresh()->email)->toBe('membre@univ-annaba.dz');
});

it('accepts changing the email to an institutional address', function (): void {
    $user = User::factory()->create(['email' => 'membre@univ-annaba.dz']);

    Livewire::actingAs($user)
        ->test(EditProfile::class)
        ->fillForm([
            'email' => 'nouveau.membre@univ-annaba.dz',
            // Filament requires the current password for sensitive changes.
            'currentPassword' => 'password',
        ])
        ->call('save')
        ->assertHasNoFormErrors();
});

it('grandfathers an existing out-of-domain email when it is not being changed', function (): void {
    // Admin-provisioned accounts (e.g. demo ones) may hold other domains;
    // saving the profile without touching the email must keep working.
    $user = User::factory()->create(['email' => 'admin@demo.ubma.dz', 'name' => 'Avant']);

    Livewire::actingAs($user)
        ->test(EditProfile::class)
        ->fillForm(['name' => 'Après Changement'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->name)->toBe('Après Changement');
});
