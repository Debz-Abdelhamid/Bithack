<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <form wire:submit="submit" class="space-y-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                {{ $this->form }}

                <div class="flex justify-end">
                    <x-filament::button type="submit">
                        {{ __('patrimoine.reservations.submit_request') }}
                    </x-filament::button>
                </div>
            </form>
        </div>

        <div class="space-y-3">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                {{ __('patrimoine.reservations.my_requests') }}
            </h2>

            @forelse ($this->myRequests() as $reservation)
                <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-100">
                                {{ $reservation->module_name ?? $reservation->purpose ?? '—' }}
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ $reservation->local->code }} · {{ $reservation->start_at->format('Y-m-d H:i') }}
                            </p>
                        </div>
                        <span @class([
                            'text-[10px] px-2 py-0.5 rounded font-bold uppercase tracking-wider',
                            'bg-amber-50 text-amber-700' => $reservation->status->value === 'pending',
                            'bg-teal-50 text-teal-700' => $reservation->status->value === 'confirmed',
                            'bg-red-50 text-red-600' => $reservation->status->value === 'rejected',
                            'bg-slate-100 text-slate-500' => $reservation->status->value === 'cancelled',
                        ])>
                            {{ $reservation->status->getLabel() }}
                        </span>
                    </div>

                    @if (in_array($reservation->status->value, ['pending', 'confirmed'], true))
                        <button
                            type="button"
                            wire:click="cancel({{ $reservation->id }})"
                            wire:confirm="{{ __('patrimoine.reservations.cancel_confirm') }}"
                            class="mt-2 text-xs font-medium text-red-600 hover:underline"
                        >
                            {{ __('patrimoine.fields.cancel_reservation') }}
                        </button>
                    @endif
                </div>
            @empty
                <p class="text-xs text-gray-400">{{ __('patrimoine.reservations.no_requests_yet') }}</p>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
