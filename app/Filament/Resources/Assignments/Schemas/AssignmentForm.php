<?php

namespace App\Filament\Resources\Assignments\Schemas;

use App\Models\Equipment;
use App\Models\Local;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AssignmentForm
{
    /**
     * $withSubject=false is used by the Equipment relation manager, where
     * the subject equipment is bound automatically by Filament and the
     * room select becomes the optional destination.
     */
    public static function configure(Schema $schema, bool $withSubject = true): Schema
    {
        $components = [];

        if (! $withSubject) {
            // Relation-manager variant: the equipment is bound by Filament;
            // the room select is the optional destination (relocation).
            $components[] = Select::make('local_id')
                ->label(__('patrimoine.fields.local'))
                ->relationship('local', 'name')
                ->getOptionLabelFromRecordUsing(
                    fn (Local $record): string => "{$record->code} — {$record->name}"
                )
                ->searchable(['code', 'name'])
                ->preload()
                ->nullable()
                ->helperText(__('patrimoine.fields.assignment_destination_help'))
                ->rules([
                    fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (filled($value) && ! Local::query()->whereKey($value)->exists()) {
                            $fail(__('patrimoine.validation.out_of_scope'));
                        }
                    },
                ]);
        }

        if ($withSubject) {
            $components[] = Section::make(__('patrimoine.sections.assignment_subject'))
                ->description(__('patrimoine.fields.assignment_subject_help'))
                ->columns(2)
                ->components([
                    Select::make('equipment_id')
                        ->label(__('patrimoine.resources.equipment.label'))
                        ->relationship('equipment', 'inventory_code')
                        ->getOptionLabelFromRecordUsing(
                            fn (Equipment $record): string => "{$record->inventory_code} — {$record->designation}"
                        )
                        ->searchable(['inventory_code', 'designation'])
                        ->preload()
                        ->requiredWithout('local_id')
                        ->validationMessages([
                            'required_without' => __('patrimoine.validation.subject_required'),
                        ])
                        // Hard server-side guard: the id must resolve through
                        // the FacultyScope-d query, so a faculty-bound user
                        // cannot post a foreign asset id (Security.md §3).
                        ->rules([
                            fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (filled($value) && ! Equipment::query()->whereKey($value)->exists()) {
                                    $fail(__('patrimoine.validation.out_of_scope'));
                                }
                            },
                        ]),
                    Select::make('local_id')
                        ->label(__('patrimoine.fields.local'))
                        ->relationship('local', 'name')
                        ->getOptionLabelFromRecordUsing(
                            fn (Local $record): string => "{$record->code} — {$record->name}"
                        )
                        ->searchable(['code', 'name'])
                        ->preload()
                        ->requiredWithout('equipment_id')
                        ->validationMessages([
                            'required_without' => __('patrimoine.validation.subject_required'),
                        ])
                        ->helperText(__('patrimoine.fields.assignment_local_help'))
                        ->rules([
                            fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (filled($value) && ! Local::query()->whereKey($value)->exists()) {
                                    $fail(__('patrimoine.validation.out_of_scope'));
                                }
                            },
                        ]),
                ]);
        }

        $components[] = Section::make(__('patrimoine.sections.assignment_target'))
            ->columns(2)
            ->components([
                Select::make('service_id')
                    ->label(__('patrimoine.resources.service.label'))
                    ->relationship('service', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Select::make('assigned_to_user_id')
                    ->label(__('patrimoine.fields.assigned_to'))
                    ->relationship('assignedTo', 'name')
                    ->searchable()
                    ->nullable()
                    ->helperText(__('patrimoine.fields.assigned_to_help')),
            ]);

        $components[] = Section::make(__('patrimoine.sections.assignment_period'))
            ->columns(2)
            ->components([
                DatePicker::make('start_date')
                    ->label(__('patrimoine.fields.start_date'))
                    ->default(today())
                    ->required()
                    // Target-completeness lives on start_date because it is
                    // always validated (closure rules on nullable fields are
                    // skipped when the field is empty): an assignment must
                    // point somewhere — a destination room (equipment
                    // relocation), a service, or a responsible person.
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $withSubject): void {
                            if (filled($get('service_id')) || filled($get('assigned_to_user_id'))) {
                                return;
                            }

                            // In the relation-manager variant the equipment
                            // subject is implicit (bound by Filament).
                            $hasEquipment = $withSubject ? filled($get('equipment_id')) : true;

                            if ($hasEquipment && filled($get('local_id'))) {
                                return; // Pure relocation is a valid affectation.
                            }

                            $fail(__('patrimoine.validation.target_required'));
                        },
                    ]),
                DatePicker::make('end_date')
                    ->label(__('patrimoine.fields.end_date'))
                    ->nullable()
                    ->rule('after_or_equal:start_date')
                    ->helperText(__('patrimoine.fields.end_date_help')),
                Textarea::make('notes')
                    ->label(__('patrimoine.fields.notes'))
                    ->rows(3)
                    ->columnSpanFull(),
            ]);

        return $schema->components($components);
    }
}
