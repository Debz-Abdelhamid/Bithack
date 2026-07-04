<?php

namespace App\Console\Commands;

use App\Models\User;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

/**
 * Phase 1 DoD helper: proves the database + realtime (Reverb) notification
 * chain end to end. The stored notification is the source of truth; the
 * broadcast event is only the "refresh your bell" ping (Security.md §7).
 */
class SendTestNotification extends Command
{
    protected $signature = 'patrimo:test-notification {email : Email of the recipient user}';

    protected $description = 'Send a test database notification (with realtime ping) to the given user';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->components->error("No user found for [{$email}].");

            return self::FAILURE;
        }

        Notification::make()
            ->title(__('patrimoine.notifications.test_title'))
            ->body(__('patrimoine.notifications.test_body'))
            ->success()
            ->sendToDatabase($user);

        DatabaseNotificationsSent::dispatch($user);

        $this->components->info("Notification stored for {$user->email} and realtime ping dispatched.");

        return self::SUCCESS;
    }
}
