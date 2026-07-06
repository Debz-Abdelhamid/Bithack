<?php

namespace App\Jobs;

use App\Models\User;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Phase 5 — request-row notifications only (Phases.md): requester on
 * confirm/reject/auto-reject, approver on a new pending request.
 * Security.md §7: the stored notification is the source of truth; the
 * queued Filament broadcast event it triggers is a content-free "refresh
 * your bell" ping — nothing sensitive crosses the wire twice.
 */
class SendReservationNotification implements ShouldQueue
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
