<?php

namespace App\Filament\Pages;

use App\Enums\ReservationStatus;
use App\Models\Local;
use App\Models\RoomReservation;
use App\Models\Scopes\FacultyScope;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only weekly grid (ui-design.md §5/§6 "emploi du temps") — every
 * role sees the combined `timetable` + confirmed `request` picture for a
 * chosen room. Deliberately bypasses FacultyScope: availability is public
 * campus information, the same bypass CampusMap already documents
 * (Security.md §3, Phases.md Phase 5).
 */
class ReservationAvailability extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected string $view = 'filament.pages.reservation-availability';

    public ?int $selectedLocalId = null;

    public string $weekStart;

    public static function getNavigationGroup(): ?string
    {
        return __('patrimoine.nav.patrimoine');
    }

    public static function getNavigationLabel(): string
    {
        return __('patrimoine.reservations.availability_nav_label');
    }

    public function getTitle(): string
    {
        return __('patrimoine.reservations.availability_title');
    }

    public function mount(): void
    {
        $this->weekStart = today()->startOfWeek()->toDateString();
        $this->selectedLocalId = $this->locals()->first()?->id;
    }

    /**
     * @return Collection<int, Local>
     */
    public function locals(): Collection
    {
        return Local::query()
            ->withoutGlobalScope(FacultyScope::class)
            ->orderBy('code')
            ->get();
    }

    public function selectLocal(int $localId): void
    {
        $this->selectedLocalId = $localId;
    }

    public function previousWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->addWeek()->toDateString();
    }

    /**
     * One entry per day of the selected week, each holding that day's
     * confirmed reservations for the selected room — Schema.md §9.5 keeps
     * the new build timestamp-based rather than the legacy fixed-slot grid.
     *
     * @return array<int, array{date: Carbon, label: string, reservations: Collection<int, RoomReservation>}>
     */
    public function getWeekPayload(): array
    {
        if ($this->selectedLocalId === null) {
            return [];
        }

        $start = Carbon::parse($this->weekStart)->startOfDay();
        $end = $start->copy()->addDays(6)->endOfDay();

        $reservations = RoomReservation::query()
            ->withoutGlobalScope(FacultyScope::class)
            ->where('local_id', $this->selectedLocalId)
            ->where('status', ReservationStatus::Confirmed)
            ->whereBetween('start_at', [$start, $end])
            ->with(['teacher', 'requestedBy'])
            ->orderBy('start_at')
            ->get();

        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $start->copy()->addDays($i);

            $days[] = [
                'date' => $date,
                'label' => $date->translatedFormat('D j M'),
                'reservations' => $reservations->filter(
                    fn (RoomReservation $reservation): bool => $reservation->start_at->isSameDay($date)
                )->values(),
            ];
        }

        return $days;
    }
}
