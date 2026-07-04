<?php

use App\Models\User;

beforeEach(function (): void {
    // Use the reverb broadcaster so private-channel authorization is actually
    // exercised (the null driver skips it). Signing happens locally — no
    // websocket server is contacted for /broadcasting/auth.
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'testing-key');
    config()->set('broadcasting.connections.reverb.secret', 'testing-secret');
    config()->set('broadcasting.connections.reverb.app_id', 'testing-app');

    // Channel callbacks were registered on the boot-time (null) broadcaster;
    // re-register them on the reverb broadcaster this test switched to.
    require base_path('routes/channels.php');
});

it('authorizes a user for their own private notification channel', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-App.Models.User.'.$user->id,
        ])
        ->assertOk();
});

it("denies a user access to another user's private channel", function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)
        ->post('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-App.Models.User.'.$other->id,
        ])
        ->assertForbidden();
});

it('denies guests the channel authorization endpoint entirely', function (): void {
    $user = User::factory()->create();

    $this->post('/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => 'private-App.Models.User.'.$user->id,
    ])->assertRedirect();
});
