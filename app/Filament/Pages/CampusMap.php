<?php

namespace App\Filament\Pages;

use App\Models\Building;
use App\Models\Local;
use App\Models\Scopes\FacultyScope;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\On;

/**
 * Cartographie campus — ported as-is from the legacy Patrimo-BitHack app
 * (ui-design.md §6): MapLibre GL, OpenFreeMap bright tiles, UBMA center at
 * zoom 17 / pitch 45, custom SVG flag markers, hover tooltips, click-through
 * to the rooms side panel, and the crosshair pick-a-location mode (which runs
 * through the Building update policy like any other mutation).
 *
 * The map deliberately shows ALL buildings (withoutGlobalScope) — the
 * physical campus is public knowledge and teachers book campus-wide
 * (Security.md §3); the FacultyScope guards the administrative resources.
 */
class CampusMap extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected string $view = 'filament.pages.campus-map';

    public ?int $selectedBuildingId = null;

    public bool $pickingLocation = false;

    public static function getNavigationGroup(): ?string
    {
        return __('patrimoine.nav.patrimoine');
    }

    public static function getNavigationLabel(): string
    {
        return __('patrimoine.campus_map.title');
    }

    public function getTitle(): string
    {
        return __('patrimoine.campus_map.title');
    }

    /**
     * Same JSON contract as the legacy app's map component:
     * Building { id, name, code, faculty, latitude, longitude, rooms[] }.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBuildingsPayload(): array
    {
        return Building::query()
            ->withoutGlobalScope(FacultyScope::class)
            ->with([
                'faculty',
                'locals' => fn ($query) => $query->withoutGlobalScope(FacultyScope::class),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Building $building): array => [
                'id' => $building->id,
                'name' => $building->name,
                'code' => $building->code,
                'faculty' => $building->faculty->name ?? __('patrimoine.fields.central_shared'),
                'latitude' => $building->latitude,
                'longitude' => $building->longitude,
                'status' => $building->status->value,
                'rooms' => $building->locals->map(fn (Local $local): array => [
                    'id' => $local->id,
                    'name' => $local->name,
                    'code' => $local->code,
                    'type' => $local->type->getLabel(),
                    'capacity' => $local->capacity,
                    'floor' => $local->floor,
                    'status' => $local->status->value,
                    'status_label' => $local->status->getLabel(),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSelectedBuilding(): ?array
    {
        if ($this->selectedBuildingId === null) {
            return null;
        }

        return collect($this->getBuildingsPayload())
            ->firstWhere('id', $this->selectedBuildingId);
    }

    public function canEditBuildings(): bool
    {
        return auth()->user()?->can('Update:Building') ?? false;
    }

    #[On('campus-building-selected')]
    public function selectBuilding(int $id): void
    {
        $this->selectedBuildingId = $id;
        $this->pickingLocation = false;
    }

    /**
     * Selection from the side-panel dropdown (works for buildings without
     * coordinates too — the fix for the "can't place an unplaced building"
     * chicken-and-egg). Tells the map to highlight/fly when placed.
     */
    public function selectBuildingFromList(int $id): void
    {
        $this->selectedBuildingId = $id;
        $this->pickingLocation = false;

        $building = collect($this->getBuildingsPayload())->firstWhere('id', $id);

        $this->dispatch(
            'campus-select-building',
            id: $id,
            latitude: $building['latitude'] ?? null,
            longitude: $building['longitude'] ?? null,
        );
    }

    public function selectedBuildingIsPlaced(): bool
    {
        $selected = $this->getSelectedBuilding();

        return $selected !== null
            && $selected['latitude'] !== null
            && $selected['longitude'] !== null;
    }

    public function startPicking(): void
    {
        abort_unless($this->canEditBuildings(), 403);

        $this->pickingLocation = true;
        $this->dispatch('campus-picking-changed', picking: true);
    }

    public function cancelPicking(): void
    {
        $this->pickingLocation = false;
        $this->dispatch('campus-picking-changed', picking: false);
    }

    #[On('campus-coordinates-picked')]
    public function setCoordinates(float $lat, float $lng): void
    {
        // Same policy gate as any other Building mutation — the drag/pick
        // interaction never bypasses authorization (Claude.md §7).
        abort_unless($this->canEditBuildings(), 403);

        if ($this->selectedBuildingId === null || ! $this->pickingLocation) {
            return;
        }

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return;
        }

        $building = Building::query()
            ->withoutGlobalScope(FacultyScope::class)
            ->findOrFail($this->selectedBuildingId);

        $this->authorize('update', $building);

        $building->update([
            'latitude' => $lat,
            'longitude' => $lng,
        ]);

        $this->pickingLocation = false;

        Notification::make()
            ->title(__('patrimoine.campus_map.position_saved'))
            ->success()
            ->send();

        $this->dispatch('campus-buildings-updated', buildings: $this->getBuildingsPayload());
    }
}
