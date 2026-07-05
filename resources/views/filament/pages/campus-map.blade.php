<x-filament-panels::page>
    @vite('resources/js/campus-map.js')

    <style>
        .campus-tooltip {
            position: absolute;
            z-index: 50;
            pointer-events: none;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 7px 11px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.13);
            white-space: nowrap;
        }
        .maplibregl-ctrl-attrib { font-size: 10px !important; opacity: 0.6; }
    </style>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- The map itself: initialized once, opted out of Livewire DOM diffing --}}
        <div class="lg:col-span-2" wire:ignore>
            <div
                id="campus-map"
                data-buildings='@json($this->getBuildingsPayload())'
                class="relative w-full overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700"
                style="height: 34rem;"
            ></div>
        </div>

        {{-- Side panel: selected building + rooms (same click-through as the legacy app) --}}
        <div class="space-y-4">
            @if ($selected = $this->getSelectedBuilding())
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h2 class="text-base font-semibold">{{ $selected['name'] }}</h2>
                            <p class="text-sm text-gray-500">
                                {{ $selected['code'] }} · {{ $selected['faculty'] }}
                            </p>
                        </div>
                        <span class="text-xs text-gray-400">{{ count($selected['rooms']) }} {{ __('patrimoine.campus_map.rooms') }}</span>
                    </div>

                    @if ($this->canEditBuildings())
                        <div class="mt-3">
                            @if ($this->pickingLocation)
                                <p class="text-sm font-medium text-warning-600">
                                    {{ __('patrimoine.campus_map.picking_hint') }}
                                </p>
                                <x-filament::button color="gray" size="sm" class="mt-2" wire:click="cancelPicking">
                                    {{ __('patrimoine.campus_map.cancel') }}
                                </x-filament::button>
                            @else
                                <x-filament::button size="sm" wire:click="startPicking">
                                    {{ __('patrimoine.campus_map.set_position') }}
                                </x-filament::button>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <div class="border-b border-gray-100 px-4 py-3 text-sm font-semibold dark:border-gray-800">
                        {{ __('patrimoine.campus_map.rooms') }}
                    </div>
                    <ul class="max-h-80 divide-y divide-gray-100 overflow-y-auto dark:divide-gray-800">
                        @forelse ($selected['rooms'] as $room)
                            <li class="flex items-center justify-between gap-2 px-4 py-2.5">
                                <div>
                                    <p class="text-sm font-medium">{{ $room['name'] }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $room['code'] }} · {{ $room['type'] }}
                                        @if ($room['capacity'])
                                            · {{ $room['capacity'] }} {{ __('patrimoine.campus_map.seats') }}
                                        @endif
                                    </p>
                                </div>
                                <x-filament::badge :color="match ($room['status']) {
                                    'available' => 'success',
                                    'occupied' => 'info',
                                    'under_maintenance' => 'warning',
                                    default => 'danger',
                                }">
                                    {{ $room['status_label'] }}
                                </x-filament::badge>
                            </li>
                        @empty
                            <li class="px-4 py-6 text-center text-sm text-gray-400">
                                {{ __('patrimoine.campus_map.no_rooms') }}
                            </li>
                        @endforelse
                    </ul>
                </div>
            @else
                <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-400 dark:border-gray-700">
                    {{ __('patrimoine.campus_map.select_hint') }}
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
