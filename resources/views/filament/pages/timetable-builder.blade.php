@php
    use App\Filament\Pages\TimetableBuilder;

    $slots = $this->getGridSlots();
@endphp

<x-filament-panels::page>
    <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap items-end gap-4">
            <div class="min-w-[16rem]">
                <label for="timetable-department-select" class="mb-1 block text-xs font-medium text-gray-500">
                    {{ __('patrimoine.resources.department.label') }}
                </label>
                <select
                    id="timetable-department-select"
                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                    wire:model.live="departmentId"
                >
                    @foreach ($this->departments() as $department)
                        <option value="{{ $department->id }}">{{ $department->faculty->name }} — {{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[16rem]">
                <label for="timetable-term-select" class="mb-1 block text-xs font-medium text-gray-500">
                    {{ __('patrimoine.fields.academic_term') }}
                </label>
                <select
                    id="timetable-term-select"
                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                    wire:model.live="academicTermId"
                >
                    @foreach ($this->academicTerms() as $term)
                        <option value="{{ $term->id }}">{{ $term->label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
        {{-- Weekly grid — fixed time-slot rows × Sat–Thu columns, matching the legacy app exactly --}}
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="w-fit min-w-full">
                <div class="grid border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800" style="grid-template-columns: 130px repeat(6, minmax(150px, 1fr));">
                    <div class="p-2 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                        {{ __('patrimoine.timetable.time_slot') }}
                    </div>
                    @foreach (TimetableBuilder::WEEK_DAYS as $day)
                        <div class="border-l border-gray-200 p-2 text-[12px] font-semibold text-gray-800 dark:border-gray-700 dark:text-gray-100">
                            {{ __('patrimoine.week_days.'.$day['label']) }}
                        </div>
                    @endforeach
                </div>

                @foreach (TimetableBuilder::TIME_SLOTS as $slot)
                    <div class="grid border-b border-gray-200 dark:border-gray-700" style="grid-template-columns: 130px repeat(6, minmax(150px, 1fr));">
                        <div class="bg-white p-2 text-[11px] font-semibold text-gray-700 dark:bg-gray-900 dark:text-gray-200">
                            {{ $slot['label'] }}
                        </div>
                        @foreach (TimetableBuilder::WEEK_DAYS as $day)
                            @php
                                $reservation = $slots["{$day['dow']}-{$slot['label']}"] ?? null;
                            @endphp
                            <div class="min-h-[84px] border-l border-gray-200 bg-white p-1.5 dark:border-gray-700 dark:bg-gray-900">
                                @if ($reservation)
                                    <div class="group relative rounded-lg border-l-4 border-l-primary-600 bg-primary-50 p-2 text-xs dark:bg-primary-900/20">
                                        <div class="font-semibold text-gray-800 dark:text-gray-100">{{ $reservation->module_name }}</div>
                                        <div class="mt-0.5 text-gray-500">{{ $reservation->teacher?->name }}</div>
                                        <div class="text-gray-500">{{ $reservation->local->code }}</div>
                                        <button
                                            type="button"
                                            wire:click="cancelSlot({{ $reservation->id }})"
                                            wire:confirm="{{ __('patrimoine.timetable.cancel_confirm') }}"
                                            class="absolute right-1 top-1 hidden h-4 w-4 items-center justify-center rounded-full text-gray-400 hover:bg-red-50 hover:text-red-600 group-hover:flex"
                                            title="{{ __('patrimoine.fields.cancel_reservation') }}"
                                        >
                                            &times;
                                        </button>
                                    </div>
                                @else
                                    <span class="text-[10px] text-gray-300">{{ __('patrimoine.timetable.empty_slot') }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Add-to-timetable panel --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <h3 class="mb-3 text-sm font-semibold text-gray-800 dark:text-gray-100">
                {{ __('patrimoine.timetable.add_slot') }}
            </h3>

            <form wire:submit="submit" class="space-y-3">
                {{ $this->form }}

                <x-filament::button type="submit" class="w-full">
                    {{ __('patrimoine.timetable.add_slot') }}
                </x-filament::button>
            </form>
        </div>
    </div>
</x-filament-panels::page>
