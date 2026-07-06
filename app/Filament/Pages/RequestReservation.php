<?php

namespace App\Filament\Pages;

use App\Enums\ReservationLevel;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Local;
use App\Models\RoomReservation;
use App\Models\Scopes\FacultyScope;
use App\Services\ReservationApprovalService;
use BackedEnum;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Enseignant-only ad-hoc/one-off booking (Security.md §3, Phases.md
 * Phase 5): campus-wide room search (own faculty_id never filters what
 * they can request — FacultyScope deliberately bypassed on the local
 * picker), starts `pending`, routed to the room's-faculty N2 (or A3 for
 * shared rooms). N2/A3 never land here — they enter the timetable
 * directly from RoomReservationResource instead.
 *
 * @property Schema $form
 */
class RequestReservation extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected string $view = 'filament.pages.request-reservation';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('patrimoine.nav.patrimoine');
    }

    public static function getNavigationLabel(): string
    {
        return __('patrimoine.reservations.request_nav_label');
    }

    public function getTitle(): string
    {
        return __('patrimoine.reservations.request_title');
    }

    /**
     * Visible only to the ad-hoc requester role — N2/A3 already manage
     * reservations from the admin resource and would otherwise see this
     * nav item redundantly (both hold Create:RoomReservation too).
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->can('create', RoomReservation::class)
            && ! $user->can('manageTimetable', RoomReservation::class);
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Select::make('local_id')
                    ->label(__('patrimoine.fields.local'))
                    ->options(
                        fn (): array => Local::query()
                            ->withoutGlobalScope(FacultyScope::class)
                            ->get()
                            ->mapWithKeys(fn (Local $local): array => [$local->id => "{$local->code} — {$local->name}"])
                            ->all()
                    )
                    ->searchable()
                    ->required(),
                TextInput::make('module_name')
                    ->label(__('patrimoine.fields.module_name'))
                    ->maxLength(255)
                    ->live()
                    ->helperText(__('patrimoine.fields.module_name_help')),
                Select::make('level')
                    ->label(__('patrimoine.fields.level'))
                    ->options(ReservationLevel::class)
                    ->required(fn (Get $get): bool => filled($get('module_name'))),
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

                            $capacity = Local::query()
                                ->withoutGlobalScope(FacultyScope::class)
                                ->whereKey($get('local_id'))
                                ->value('capacity');

                            if ($capacity !== null && (int) $value > $capacity) {
                                $fail(__('patrimoine.validation.attendees_exceed_capacity', ['capacity' => $capacity]));
                            }
                        },
                    ]),
                TextInput::make('purpose')
                    ->label(__('patrimoine.fields.purpose'))
                    ->maxLength(255)
                    ->required(fn (Get $get): bool => blank($get('module_name')))
                    ->helperText(__('patrimoine.fields.purpose_help')),
                DateTimePicker::make('start_at')
                    ->label(__('patrimoine.fields.start_at'))
                    ->required()
                    ->seconds(false)
                    ->native(false)
                    ->minDate(now()),
                DateTimePicker::make('end_at')
                    ->label(__('patrimoine.fields.end_at'))
                    ->required()
                    ->seconds(false)
                    ->native(false)
                    ->after('start_at'),
            ]);
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        $key = 'reservation-request:'.auth()->id();
        $maxPerHour = (int) config('patrimo.reservations.request_max_per_hour');

        if (RateLimiter::tooManyAttempts($key, $maxPerHour)) {
            Notification::make()
                ->danger()
                ->title(__('patrimoine.reservations.rate_limited', ['seconds' => RateLimiter::availableIn($key)]))
                ->send();

            return;
        }

        RateLimiter::hit($key, decaySeconds: 3600);

        $start = Carbon::parse($data['start_at']);
        $end = Carbon::parse($data['end_at']);

        if (RoomReservation::hasConfirmedOverlap((int) $data['local_id'], $start, $end)) {
            Notification::make()
                ->danger()
                ->title(__('patrimoine.reservations.slot_taken'))
                ->send();

            return;
        }

        $reservation = RoomReservation::create([
            ...$data,
            'source' => ReservationSource::Request,
            'status' => ReservationStatus::Pending,
            'requested_by_user_id' => auth()->id(),
            'teacher_user_id' => auth()->id(),
        ]);

        app(ReservationApprovalService::class)->notifyApprover($reservation);

        Notification::make()
            ->success()
            ->title(__('patrimoine.reservations.request_submitted'))
            ->send();

        $this->form->fill();
    }

    /**
     * @return Collection<int, RoomReservation>
     */
    public function myRequests(): Collection
    {
        // A teacher's own requests routinely target rooms outside their
        // faculty (campus-wide booking) — bypassing FacultyScope on the
        // outer query alone does not stop it from being reapplied to the
        // eager-loaded `local` relation, which would otherwise come back
        // null for those exact rows.
        return RoomReservation::query()
            ->withoutGlobalScope(FacultyScope::class)
            ->where('requested_by_user_id', auth()->id())
            ->with(['local' => function (Relation $query): Relation {
                return $query
                    ->withoutGlobalScope(FacultyScope::class)
                    ->with(['building' => fn (Relation $buildingQuery): Relation => $buildingQuery->withoutGlobalScope(FacultyScope::class)]);
            }])
            ->latest('start_at')
            ->limit(20)
            ->get();
    }

    public function cancel(int $reservationId): void
    {
        $reservation = RoomReservation::query()
            ->withoutGlobalScope(FacultyScope::class)
            ->findOrFail($reservationId);

        abort_unless(auth()->user()?->can('cancel', $reservation) ?? false, 403);

        $reservation->update(['status' => ReservationStatus::Cancelled]);

        Notification::make()
            ->success()
            ->title(__('patrimoine.reservations.cancelled'))
            ->send();
    }
}
