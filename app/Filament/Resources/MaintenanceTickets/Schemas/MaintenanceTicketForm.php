<?php

namespace App\Filament\Resources\MaintenanceTickets\Schemas;

use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Models\Equipment;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * A3's manual-entry counterpart to the automatic QR-scan flow
 * (App\Http\Controllers\AnomalyReportController) — same fields, minus
 * `reference`/`sla_due_at` (observer-computed) and `reported_by_user_id`
 * (set from the acting user).
 */
class MaintenanceTicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('patrimoine.sections.ticket_subject'))
                    ->columns(2)
                    ->components([
                        Select::make('equipment_id')
                            ->label(__('patrimoine.fields.equipment'))
                            ->relationship('equipment', 'designation')
                            ->getOptionLabelFromRecordUsing(
                                fn (Equipment $record): string => "{$record->inventory_code} — {$record->designation}"
                            )
                            ->searchable(['inventory_code', 'designation'])
                            ->nullable(),
                        Select::make('local_id')
                            ->label(__('patrimoine.fields.local'))
                            ->relationship('local', 'name')
                            ->searchable(['code', 'name'])
                            ->nullable()
                            ->helperText(__('patrimoine.fields.ticket_local_help')),
                    ]),
                Section::make(__('patrimoine.sections.ticket_details'))
                    ->columns(2)
                    ->components([
                        Select::make('source')
                            ->label(__('patrimoine.fields.source'))
                            ->options(TicketSource::class)
                            ->default(TicketSource::Manual)
                            ->required(),
                        Select::make('priority')
                            ->label(__('patrimoine.fields.priority'))
                            ->options(TicketPriority::class)
                            ->default(TicketPriority::Standard)
                            ->required(),
                        Select::make('category')
                            ->label(__('patrimoine.fields.category'))
                            ->options([
                                'informatique' => 'informatique',
                                'mobilier' => 'mobilier',
                                'electrique' => 'electrique',
                                'plomberie' => 'plomberie',
                                'audiovisuel' => 'audiovisuel',
                                'securite' => 'securite',
                            ])
                            ->nullable(),
                        Select::make('status')
                            ->label(__('patrimoine.fields.status'))
                            ->options(TicketStatus::class)
                            ->default(TicketStatus::New)
                            ->required()
                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                            ->dehydrated(fn (string $operation): bool => $operation !== 'edit')
                            ->helperText(fn (string $operation): ?string => $operation === 'edit'
                                ? __('patrimoine.fields.status_change_help')
                                : null),
                        Select::make('assigned_service_id')
                            ->label(__('patrimoine.fields.assigned_service'))
                            ->relationship('assignedService', 'name')
                            ->searchable()
                            ->nullable(),
                        Textarea::make('description')
                            ->label(__('patrimoine.fields.description'))
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
