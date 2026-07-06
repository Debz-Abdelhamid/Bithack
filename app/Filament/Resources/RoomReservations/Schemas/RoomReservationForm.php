<?php

namespace App\Filament\Resources\RoomReservations\Schemas;

use App\Enums\ReservationLevel;
use App\Models\Local;
use App\Models\User;
use App\Support\RoleName;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The admin resource (N2/A3) only ever creates `timetable` rows — the
 * faculty-authored emploi du temps (decision 2026-07-06). `request` rows
 * come exclusively from the Enseignant ad-hoc booking page; N2/A3 only
 * confirm/reject those from the table, never hand-author them here.
 */
class RoomReservationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('patrimoine.sections.reservation_slot'))
                ->columns(2)
                ->components([
                    Select::make('local_id')
                        ->label(__('patrimoine.fields.local'))
                        ->relationship('local', 'name', modifyQueryUsing: fn (Builder $query): Builder => self::scopeLocalOptions($query))
                        ->getOptionLabelFromRecordUsing(
                            fn (Local $record): string => "{$record->code} — {$record->name}"
                        )
                        ->searchable(['code', 'name'])
                        ->preload()
                        ->required()
                        ->rules([
                            fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (filled($value) && ! self::scopeLocalOptions(Local::query())->whereKey($value)->exists()) {
                                    $fail(__('patrimoine.validation.out_of_scope'));
                                }
                            },
                        ]),
                    Select::make('teacher_user_id')
                        ->label(__('patrimoine.fields.teacher'))
                        ->relationship(
                            'teacher',
                            'name',
                            modifyQueryUsing: fn (Builder $query): Builder => $query->whereHas(
                                'roles',
                                fn (Builder $roleQuery): Builder => $roleQuery->where('name', RoleName::ENSEIGNANT),
                            ),
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText(__('patrimoine.fields.teacher_help')),
                ]),
            Section::make(__('patrimoine.sections.reservation_course'))
                ->columns(2)
                ->components([
                    TextInput::make('module_name')
                        ->label(__('patrimoine.fields.module_name'))
                        ->required()
                        ->maxLength(255),
                    Select::make('level')
                        ->label(__('patrimoine.fields.level'))
                        ->options(ReservationLevel::class)
                        ->required(),
                    TextInput::make('department')
                        ->label(__('patrimoine.fields.department'))
                        ->maxLength(255),
                    TextInput::make('student_group')
                        ->label(__('patrimoine.fields.student_group'))
                        ->maxLength(255),
                    TextInput::make('attendees_count')
                        ->label(__('patrimoine.fields.attendees_count'))
                        ->numeric()
                        ->minValue(1)
                        ->rules([
                            fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                if (blank($value) || blank($get('local_id'))) {
                                    return;
                                }

                                $capacity = Local::query()->whereKey($get('local_id'))->value('capacity');

                                if ($capacity !== null && (int) $value > $capacity) {
                                    $fail(__('patrimoine.validation.attendees_exceed_capacity', ['capacity' => $capacity]));
                                }
                            },
                        ]),
                ]),
            Section::make(__('patrimoine.sections.reservation_period'))
                ->columns(2)
                ->components([
                    DateTimePicker::make('start_at')
                        ->label(__('patrimoine.fields.start_at'))
                        ->required()
                        ->seconds(false)
                        ->native(false),
                    DateTimePicker::make('end_at')
                        ->label(__('patrimoine.fields.end_at'))
                        ->required()
                        ->seconds(false)
                        ->native(false)
                        ->after('start_at'),
                    Toggle::make('repeat_weekly')
                        ->label(__('patrimoine.fields.repeat_weekly'))
                        ->live(),
                    DatePicker::make('repeat_until')
                        ->label(__('patrimoine.fields.repeat_until'))
                        ->native(false)
                        ->visible(fn (Get $get): bool => (bool) $get('repeat_weekly'))
                        ->required(fn (Get $get): bool => (bool) $get('repeat_weekly'))
                        ->after('start_at')
                        ->rules([
                            fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                if (blank($value) || blank($get('start_at'))) {
                                    return;
                                }

                                $months = (int) config('patrimo.reservations.max_recurrence_months');
                                $cap = Carbon::parse($get('start_at'))->addMonths($months);

                                if (Carbon::parse($value)->gt($cap)) {
                                    $fail(__('patrimoine.validation.recurrence_too_long', ['months' => $months]));
                                }
                            },
                        ]),
                ]),
        ]);
    }

    /**
     * @param  Builder<Local>  $query
     * @return Builder<Local>
     */
    public static function scopeLocalOptions(Builder $query): Builder
    {
        $user = auth()->user();

        if ($user instanceof User && $user->faculty_id !== null && ! $user->can('ViewAcrossFaculties')) {
            // N2 owns exactly their own faculty's timetable — shared/
            // central rooms are A3's responsibility (Security.md §3).
            $query->whereHas('building', fn (Builder $q): Builder => $q->where('faculty_id', $user->faculty_id));
        }

        return $query;
    }
}
