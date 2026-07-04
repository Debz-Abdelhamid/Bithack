<?php

use App\Filament\Auth\Register;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\RoleName;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    config()->set('patrimo.registration.allowed_domains', ['univ-annaba.dz']);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('shows the registration page', function (): void {
    $this->get('/admin/register')->assertOk();
});

it('rejects email addresses outside the university domain', function (): void {
    Livewire::test(Register::class)
        ->fillForm([
            'name' => 'Intrus Externe',
            'email' => 'intrus@gmail.com',
            'password' => 'Motdepasse!Solide123',
            'passwordConfirmation' => 'Motdepasse!Solide123',
        ])
        ->call('register')
        ->assertHasFormErrors(['email']);

    expect(User::query()->where('email', 'intrus@gmail.com')->exists())->toBeFalse();
});

it('registers a university address with the tout_utilisateur role, unverified', function (): void {
    Notification::fake();

    Livewire::test(Register::class)
        ->fillForm([
            'name' => 'Etudiant Test',
            'email' => 'etudiant.test@univ-annaba.dz',
            'password' => 'Motdepasse!Solide123',
            'passwordConfirmation' => 'Motdepasse!Solide123',
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'etudiant.test@univ-annaba.dz')->firstOrFail();

    expect($user->hasRole(RoleName::TOUT_UTILISATEUR))->toBeTrue()
        ->and($user->roles)->toHaveCount(1)
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and(Hash::check('Motdepasse!Solide123', $user->password))->toBeTrue();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('holds unverified self-registered users at the email verification prompt', function (): void {
    $user = User::factory()->unverified()->create();
    $user->assignRole(RoleName::TOUT_UTILISATEUR);

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect();
});

it('lets the user in once verified — with least-privilege access only', function (): void {
    $user = User::factory()->create(); // factory users are verified
    $user->assignRole(RoleName::TOUT_UTILISATEUR);

    $this->actingAs($user)->get('/admin')->assertOk();

    $this->actingAs($user)
        ->get(UserResource::getUrl('index'))
        ->assertForbidden();
});

it('caps registrations per IP per hour', function (): void {
    config()->set('patrimo.registration.max_per_hour_per_ip', 1);

    Livewire::test(Register::class)
        ->fillForm([
            'name' => 'Premier Etudiant',
            'email' => 'premier@univ-annaba.dz',
            'password' => 'Motdepasse!Solide123',
            'passwordConfirmation' => 'Motdepasse!Solide123',
        ])
        ->call('register');

    expect(User::query()->where('email', 'premier@univ-annaba.dz')->exists())->toBeTrue();

    auth()->logout();

    Livewire::test(Register::class)
        ->fillForm([
            'name' => 'Deuxieme Etudiant',
            'email' => 'deuxieme@univ-annaba.dz',
            'password' => 'Motdepasse!Solide123',
            'passwordConfirmation' => 'Motdepasse!Solide123',
        ])
        ->call('register');

    expect(User::query()->where('email', 'deuxieme@univ-annaba.dz')->exists())->toBeFalse();
});

it('keeps seeded demo accounts pre-verified so the gate never blocks them', function (): void {
    $this->seed(DemoSeeder::class);

    $admin = User::query()->where('email', 'admin@demo.ubma.dz')->firstOrFail();

    expect($admin->hasVerifiedEmail())->toBeTrue();
});
