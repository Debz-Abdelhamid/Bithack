<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast channels (Security.md §5)
|--------------------------------------------------------------------------
| Private channels only — realtime is a notification delivery hint, never a
| data carrier. A user may subscribe exclusively to their own channel.
*/

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return $user->id === $id;
});
