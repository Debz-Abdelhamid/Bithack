<?php

namespace App\Filament\Resources\MaintenanceTickets\Pages;

use App\Enums\TicketStatus;
use App\Exceptions\InvalidTicketTransitionException;
use App\Filament\Resources\MaintenanceTickets\MaintenanceTicketResource;
use App\Models\MaintenanceTicket;
use App\Services\TicketWorkflowService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * Manual status changes go through the same two actions the Kanban board's
 * drag ultimately calls too (`TicketWorkflowService`) — never a free-form
 * status field on this page (Phases.md Phase 7 DoD: one enforcement path).
 */
class ViewMaintenanceTicket extends ViewRecord
{
    protected static string $resource = MaintenanceTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('advance')
                ->label(function (MaintenanceTicket $record): string {
                    $next = $record->status->next();

                    return $next === null
                        ? __('patrimoine.board.advance')
                        : __('patrimoine.board.advance_to', ['status' => $next->getLabel()]);
                })
                ->icon(Heroicon::OutlinedArrowRight)
                ->visible(fn (MaintenanceTicket $record): bool => $record->status->next() !== null
                    && (auth()->user()?->can('update', $record) ?? false))
                ->requiresConfirmation()
                ->action(function (MaintenanceTicket $record): void {
                    $next = $record->status->next();

                    if ($next === null) {
                        return;
                    }

                    $this->transition($record, $next);
                }),
            Action::make('cancel')
                ->label(__('patrimoine.board.cancel_ticket'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (MaintenanceTicket $record): bool => ! $record->status->isTerminal()
                    && (auth()->user()?->can('update', $record) ?? false))
                ->requiresConfirmation()
                ->action(fn (MaintenanceTicket $record) => $this->transition($record, TicketStatus::Cancelled)),
            EditAction::make(),
        ];
    }

    private function transition(MaintenanceTicket $record, TicketStatus $to): void
    {
        try {
            app(TicketWorkflowService::class)->transition($record, $to, auth()->user());
        } catch (InvalidTicketTransitionException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title(__('patrimoine.board.moved', ['status' => $to->getLabel()]))->send();
    }
}
