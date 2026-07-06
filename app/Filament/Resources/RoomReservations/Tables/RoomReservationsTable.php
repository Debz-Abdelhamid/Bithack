<?php

namespace App\Filament\Resources\RoomReservations\Tables;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\RoomReservation;
use App\Services\ReservationApprovalService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RoomReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query
                    ->with(['local.building', 'requestedBy', 'teacher', 'approvedBy'])
                    ->latest('start_at')
            )
            ->columns([
                TextColumn::make('source')
                    ->label(__('patrimoine.fields.source'))
                    ->badge(),
                TextColumn::make('local.code')
                    ->label(__('patrimoine.fields.local'))
                    ->description(fn (RoomReservation $record): string => $record->local->building->name)
                    ->sortable(),
                TextColumn::make('subject')
                    ->label(__('patrimoine.fields.reservation_subject'))
                    ->state(
                        fn (RoomReservation $record): string => $record->module_name
                            ?? $record->purpose
                            ?? '—'
                    )
                    // teacher_user_id is genuinely nullable (migration) — Larastan infers
                    // BelongsTo as non-null without DB-aware reflection; false positive.
                    ->description(fn (RoomReservation $record): string => $record->teacher?->name ?? $record->requestedBy->name), // @phpstan-ignore nullsafe.neverNull
                TextColumn::make('start_at')
                    ->label(__('patrimoine.fields.start_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('end_at')
                    ->label(__('patrimoine.fields.end_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('recurring_rule')
                    ->label(__('patrimoine.fields.repeat_weekly'))
                    ->placeholder('—')
                    ->fontFamily(FontFamily::Mono)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label(__('patrimoine.fields.status'))
                    ->badge(),
                TextColumn::make('requestedBy.name')
                    ->label(__('patrimoine.fields.requested_by'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->label(__('patrimoine.fields.source'))
                    ->options(ReservationSource::class),
                SelectFilter::make('status')
                    ->label(__('patrimoine.fields.status'))
                    ->options(ReservationStatus::class)
                    ->default(ReservationStatus::Pending->value),
                SelectFilter::make('local')
                    ->label(__('patrimoine.fields.local'))
                    ->relationship('local', 'name'),
            ])
            ->recordActions([
                self::confirmAction(),
                self::rejectAction(),
                EditAction::make()
                    ->visible(fn (RoomReservation $record): bool => $record->source === ReservationSource::Timetable),
            ]);
    }

    /**
     * Phase 5 DoD + PROGRESS.md open question #5 default: confirming a
     * pending request re-checks for a confirmed overlap (blocked if one
     * exists) and auto-rejects any OTHER pending requests competing for
     * the same room/time.
     */
    public static function confirmAction(): Action
    {
        return Action::make('confirm')
            ->label(__('patrimoine.fields.confirm'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->visible(
                fn (RoomReservation $record): bool => $record->source === ReservationSource::Request
                    && $record->status === ReservationStatus::Pending
                    && (auth()->user()?->can('approve', $record) ?? false)
            )
            ->action(fn (RoomReservation $record) => app(ReservationApprovalService::class)->confirm($record, auth()->user()));
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('patrimoine.fields.reject'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->schema([
                Textarea::make('reason')
                    ->label(__('patrimoine.fields.rejection_reason'))
                    ->rows(2),
            ])
            ->visible(
                fn (RoomReservation $record): bool => $record->source === ReservationSource::Request
                    && $record->status === ReservationStatus::Pending
                    && (auth()->user()?->can('approve', $record) ?? false)
            )
            ->action(
                fn (RoomReservation $record, array $data) => app(ReservationApprovalService::class)
                    ->reject($record, auth()->user(), $data['reason'] ?? null)
            );
    }
}
