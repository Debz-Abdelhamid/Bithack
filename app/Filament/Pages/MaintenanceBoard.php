<?php

namespace App\Filament\Pages;

use App\Enums\TicketStatus;
use App\Exceptions\InvalidTicketTransitionException;
use App\Models\MaintenanceTicket;
use App\Services\TicketWorkflowService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Phases.md Phase 7 — the drag-and-drop Kanban board, ported from the
 * legacy `TicketsBoardView.tsx` (native HTML5 drag-and-drop, no client
 * library — confirmed by reading the actual component, not guessed).
 * Columns = `TicketStatus::boardColumns()`; every drop calls the same
 * `TicketWorkflowService` a manual status change would use, so a drag can
 * never bypass the state machine (Phase 7 DoD).
 */
class MaintenanceBoard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected string $view = 'filament.pages.maintenance-board';

    public static function getNavigationGroup(): ?string
    {
        return __('patrimoine.nav.patrimoine');
    }

    public static function getNavigationLabel(): string
    {
        return __('patrimoine.board.nav_label');
    }

    public function getTitle(): string
    {
        return __('patrimoine.board.title');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', MaintenanceTicket::class) ?? false;
    }

    /**
     * @return array<string, Collection<int, MaintenanceTicket>>
     */
    public function columns(): array
    {
        $tickets = MaintenanceTicket::query()
            ->whereIn('status', TicketStatus::boardColumns())
            ->with(['equipment.local.building', 'local.building', 'assignedService', 'interventions.technician'])
            ->latest('created_at')
            ->get();

        $grouped = $tickets->groupBy(fn (MaintenanceTicket $ticket): string => $ticket->status->value);

        $columns = [];

        foreach (TicketStatus::boardColumns() as $status) {
            $columns[$status->value] = $grouped->get($status->value, collect());
        }

        return $columns;
    }

    public function moveTicket(int $ticketId, string $targetStatus): void
    {
        $ticket = MaintenanceTicket::query()->findOrFail($ticketId);

        abort_unless(auth()->user()?->can('update', $ticket) ?? false, 403);

        $target = TicketStatus::from($targetStatus);

        try {
            app(TicketWorkflowService::class)->transition($ticket, $target, auth()->user());
        } catch (InvalidTicketTransitionException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title(__('patrimoine.board.moved', ['status' => $target->getLabel()]))->send();
    }
}
