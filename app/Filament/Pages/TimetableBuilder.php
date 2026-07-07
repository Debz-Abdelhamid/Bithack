<?php

namespace App\Filament\Pages;

use App\Enums\ReservationLevel;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\AcademicTerm;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Local;
use App\Models\RoomReservation;
use App\Models\User;
use App\Services\TimetableSlotService;
use App\Support\RoleName;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Phase 5 addendum (2026-07-06) — the visual weekly timetable grid N2/A3
 * use to actually FILL a department's timetable, one academic term at a
 * time (ui-design.md §6 look: fixed time-slot rows × day columns, ported
 * from the legacy app's exact 6 periods and Sat–Thu week). The plain
 * RoomReservationResource create form still works as a fallback, but this
 * page is the primary, department/term-scoped entry point.
 *
 * @property Schema $form
 */
class TimetableBuilder extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected string $view = 'filament.pages.timetable-builder';

    /**
     * (label, start "HH:MM", end "HH:MM") — the legacy app's exact 6
     * fixed daily periods (ui-design.md §9.5 / Schema.md §9.5).
     *
     * @var list<array{label: string, start: string, end: string}>
     */
    public const TIME_SLOTS = [
        ['label' => '08:00 - 09:30', 'start' => '08:00', 'end' => '09:30'],
        ['label' => '09:45 - 11:15', 'start' => '09:45', 'end' => '11:15'],
        ['label' => '11:30 - 13:00', 'start' => '11:30', 'end' => '13:00'],
        ['label' => '14:00 - 15:30', 'start' => '14:00', 'end' => '15:30'],
        ['label' => '15:45 - 16:15', 'start' => '15:45', 'end' => '16:15'],
        ['label' => '16:30 - 17:45', 'start' => '16:30', 'end' => '17:45'],
    ];

    /**
     * (label, Carbon::dayOfWeek value) — the Algerian academic week,
     * confirmed from the legacy app's own seed data (Sat–Thu, Friday off).
     *
     * @var list<array{label: string, dow: int}>
     */
    public const WEEK_DAYS = [
        ['label' => 'Sat', 'dow' => Carbon::SATURDAY],
        ['label' => 'Sun', 'dow' => Carbon::SUNDAY],
        ['label' => 'Mon', 'dow' => Carbon::MONDAY],
        ['label' => 'Tue', 'dow' => Carbon::TUESDAY],
        ['label' => 'Wed', 'dow' => Carbon::WEDNESDAY],
        ['label' => 'Thu', 'dow' => Carbon::THURSDAY],
    ];

    public ?int $departmentId = null;

    public ?int $academicTermId = null;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('patrimoine.nav.patrimoine');
    }

    public static function getNavigationLabel(): string
    {
        return __('patrimoine.timetable.nav_label');
    }

    public function getTitle(): string
    {
        return __('patrimoine.timetable.title');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('manageTimetable', RoomReservation::class);
    }

    public function mount(): void
    {
        $this->departmentId = Department::query()->orderBy('name')->value('id');
        $this->academicTermId = AcademicTerm::current()->value('id') ?? AcademicTerm::query()->orderByDesc('start_date')->value('id');
        $this->form->fill();
    }

    /**
     * @return Collection<int, AcademicTerm>
     */
    public function academicTerms(): Collection
    {
        return AcademicTerm::query()->orderByDesc('start_date')->get();
    }

    /**
     * "Academic Structure" sidebar tree — faculties with the departments
     * under them (Department is FacultyScope'd, so N2 only ever sees their
     * own faculty here; A3/N3 see all). Ported from the legacy app's
     * faculty→department navigation panel (ReservationsView.tsx), used here
     * as the department-picker instead of duplicating a plain <select> for
     * the same choice.
     *
     * @return Collection<int, Faculty>
     */
    public function academicStructure(): Collection
    {
        return Faculty::query()
            ->whereHas('departments')
            ->with(['departments' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();
    }

    public function selectDepartment(int $departmentId): void
    {
        $this->departmentId = $departmentId;
    }

    public function currentDepartment(): ?Department
    {
        if ($this->departmentId === null) {
            return null;
        }

        return Department::query()->with('faculty')->find($this->departmentId);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                // Plain data-collection panel, not bound to a real Eloquent
                // record — Filament's ->relationship() select assumes a
                // saveable record context and breaks here, so both selects
                // use the same lazy-search pattern as RequestReservation.
                Select::make('local_id')
                    ->label(__('patrimoine.fields.local'))
                    ->getSearchResultsUsing(
                        fn (string $search): array => $this->scopeLocalOptions(Local::query())
                            ->where(fn (Builder $query) => $query
                                ->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%"))
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (Local $local): array => [$local->id => "{$local->code} — {$local->name}"])
                            ->all()
                    )
                    ->getOptionLabelUsing(function ($value): ?string {
                        $local = $this->scopeLocalOptions(Local::query())->whereKey($value)->first();

                        return $local === null ? null : "{$local->code} — {$local->name}";
                    })
                    ->searchable()
                    ->required(),
                Select::make('teacher_user_id')
                    ->label(__('patrimoine.fields.teacher'))
                    ->getSearchResultsUsing(
                        fn (string $search): array => User::query()
                            ->whereHas('roles', fn (Builder $q): Builder => $q->where('name', RoleName::ENSEIGNANT))
                            ->where('name', 'like', "%{$search}%")
                            ->limit(50)
                            ->pluck('name', 'id')
                            ->all()
                    )
                    ->getOptionLabelUsing(fn ($value): ?string => User::query()->whereKey($value)->value('name'))
                    ->searchable()
                    ->required(),
                TextInput::make('module_name')
                    ->label(__('patrimoine.fields.module_name'))
                    ->required()
                    ->maxLength(255),
                Select::make('level')
                    ->label(__('patrimoine.fields.level'))
                    ->options(ReservationLevel::class)
                    ->required(),
                TextInput::make('student_group')
                    ->label(__('patrimoine.fields.student_group'))
                    ->maxLength(255),
                Select::make('day')
                    ->label(__('patrimoine.fields.day'))
                    ->options(collect(self::WEEK_DAYS)->pluck('label', 'dow')->all())
                    ->required(),
                Select::make('time_slot')
                    ->label(__('patrimoine.fields.time_slot'))
                    ->options(collect(self::TIME_SLOTS)->pluck('label', 'label')->all())
                    ->required(),
            ]);
    }

    /**
     * @param  Builder<Local>  $query
     * @return Builder<Local>
     */
    private function scopeLocalOptions(Builder $query): Builder
    {
        $user = auth()->user();

        if ($user instanceof User && $user->faculty_id !== null && ! $user->can('ViewAcrossFaculties')) {
            $query->whereHas('building', fn (Builder $q): Builder => $q->where('faculty_id', $user->faculty_id));
        }

        return $query;
    }

    /**
     * Grouped by recurring_group_id (or own id for a one-off row) so a
     * whole weekly series renders as a single card, keyed by
     * "{dayOfWeek}-{timeSlotLabel}" for direct grid placement.
     *
     * @return array<string, RoomReservation>
     */
    public function getGridSlots(): array
    {
        if ($this->departmentId === null || $this->academicTermId === null) {
            return [];
        }

        $reservations = RoomReservation::query()
            ->where('department_id', $this->departmentId)
            ->where('academic_term_id', $this->academicTermId)
            ->where('source', ReservationSource::Timetable)
            ->where('status', ReservationStatus::Confirmed)
            ->with(['local.building', 'teacher'])
            ->get()
            ->unique(fn (RoomReservation $r): string => $r->recurring_group_id ?? (string) $r->id);

        $slots = [];

        foreach ($reservations as $reservation) {
            $dow = $reservation->start_at->dayOfWeek;
            $timeLabel = $this->matchTimeSlotLabel($reservation->start_at->format('H:i'));

            if ($timeLabel === null) {
                continue;
            }

            $slots["{$dow}-{$timeLabel}"] = $reservation;
        }

        return $slots;
    }

    private function matchTimeSlotLabel(string $startTime): ?string
    {
        foreach (self::TIME_SLOTS as $slot) {
            if ($slot['start'] === $startTime) {
                return $slot['label'];
            }
        }

        return null;
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        $term = AcademicTerm::query()->find($this->academicTermId);

        if ($term === null || $this->departmentId === null) {
            Notification::make()->danger()->title(__('patrimoine.timetable.missing_context'))->send();

            return;
        }

        $timeSlot = collect(self::TIME_SLOTS)->firstWhere('label', $data['time_slot']);
        $dayOfWeek = (int) $data['day'];

        // Carbon::next() always skips to a LATER date, even when
        // start_date itself already falls on the target day of week.
        $termStart = $term->start_date->copy();
        $firstDate = $termStart->dayOfWeek === $dayOfWeek ? $termStart : $termStart->next($dayOfWeek);

        $startAt = $firstDate->copy()->setTimeFromTimeString($timeSlot['start']);
        $endAt = $firstDate->copy()->setTimeFromTimeString($timeSlot['end']);

        $result = app(TimetableSlotService::class)->createSeries([
            'local_id' => $data['local_id'],
            'teacher_user_id' => $data['teacher_user_id'],
            'module_name' => $data['module_name'],
            'level' => $data['level'],
            'student_group' => $data['student_group'],
            'department_id' => $this->departmentId,
            'source' => ReservationSource::Timetable,
            'status' => ReservationStatus::Confirmed,
            'requested_by_user_id' => auth()->id(),
            'start_at' => $startAt,
            'end_at' => $endAt,
        ], $term, repeatWeekly: true);

        if ($result['created'] === null) {
            Notification::make()
                ->danger()
                ->title(__('patrimoine.reservations.series_conflict', ['date' => $result['conflictDate']]))
                ->send();

            return;
        }

        Notification::make()->success()->title(__('patrimoine.timetable.slot_added'))->send();

        $this->form->fill();
    }

    public function cancelSlot(int $reservationId): void
    {
        $reservation = RoomReservation::query()->findOrFail($reservationId);

        abort_unless(auth()->user()?->can('cancel', $reservation) ?? false, 403);

        $query = RoomReservation::query()->where('status', ReservationStatus::Confirmed);

        if ($reservation->recurring_group_id !== null) {
            $query->where('recurring_group_id', $reservation->recurring_group_id);
        } else {
            $query->whereKey($reservation->id);
        }

        // Bypasses the model observer (bulk update, not per-row save) —
        // intentional: cancelling can never itself create an overlap.
        $query->update(['status' => ReservationStatus::Cancelled]);

        Notification::make()->success()->title(__('patrimoine.timetable.slot_cancelled'))->send();
    }
}
