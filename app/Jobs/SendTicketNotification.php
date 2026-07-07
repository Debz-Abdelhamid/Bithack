<?php

namespace App\Jobs;

use App\Models\User;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Phase 6 — new-ticket pings to A3 and the routed service's responsible
 * user. Security.md §7: ids/labels only, never the report free text; the
 * stored notification is the source of truth, this queued broadcast
 * event is just a content-free "refresh your bell" signal.
 */
class SendTicketNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $userId,
        public readonly string $title,
        public readonly string $body,
        public readonly string $status,
    ) {}

    public function handle(): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        Notification::make()
            ->title($this->title)
            ->body($this->body)
            ->status($this->status)
            ->sendToDatabase($user);

        DatabaseNotificationsSent::dispatch($user);
    }
}
