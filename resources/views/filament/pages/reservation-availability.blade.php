<x-filament-panels::page>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
            <div class="min-w-[14rem]">
                <label for="reservation-local-select" class="mb-1 block text-xs font-medium text-gray-500">
                    {{ __('patrimoine.fields.local') }}
                </label>
                <select
                    id="reservation-local-select"
                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                    wire:change="selectLocal($event.target.value)"
                >
                    @foreach ($this->locals() as $local)
                        <option value="{{ $local->id }}" @selected($selectedLocalId === $local->id)>
                            {{ $local->code }} — {{ $local->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    wire:click="previousWeek"
                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-800"
                >
                    &larr;
                </button>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                    {{ \Illuminate\Support\Carbon::parse($weekStart)->translatedFormat('d M Y') }}
                </span>
                <button
                    type="button"
                    wire:click="nextWeek"
                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-800"
                >
                    &rarr;
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-7">
            @forelse ($this->getWeekPayload() as $day)
                <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="mb-2 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                        {{ $day['label'] }}
                    </h3>

                    <div class="space-y-2">
                        @forelse ($day['reservations'] as $reservation)
                            <div class="rounded-lg border-l-4 border-l-primary-600 bg-primary-50 p-2 text-xs dark:bg-primary-900/20">
                                <div class="font-mono font-semibold text-primary-700 dark:text-primary-300">
                                    {{ $reservation->start_at->format('H:i') }}–{{ $reservation->end_at->format('H:i') }}
                                </div>
                                <div class="mt-0.5 font-medium text-gray-800 dark:text-gray-100">
                                    {{ $reservation->module_name ?? $reservation->purpose ?? '—' }}
                                </div>
                                <div class="text-gray-500">
                                    {{ $reservation->teacher?->name ?? $reservation->requestedBy->name }}
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-gray-400">{{ __('patrimoine.reservations.no_slots') }}</p>
                        @endforelse
                    </div>
                </div>
            @empty
                <p class="col-span-full text-sm text-gray-500">{{ __('patrimoine.reservations.no_room_selected') }}</p>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
