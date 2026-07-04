<?php

use App\Models\User;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('shows the password reset request page', function (): void {
    $this->get('/admin/password-reset/request')->assertOk();
});

it('emails a reset link to a known account', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => $user->email])
        ->call('request');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('does not reveal whether an email exists', function (): void {
    Notification::fake();

    Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => 'inconnu@univ-annaba.dz'])
        ->call('request')
        ->assertHasNoFormErrors();

    Notification::assertNothingSent();
});
