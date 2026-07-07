@php
    use App\Filament\Pages\TimetableBuilder;
    use App\Enums\ReservationLevel;

    $slots = $this->getGridSlots();
    $currentDepartment = $this->currentDepartment();
    $knownStudentGroups = $this->knownStudentGroups();

    $statusClasses = [
        'success' => ['accent' => 'bg-green-600', 'bg' => 'bg-green-50 dark:bg-green-900/20', 'badge' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'],
        'warning' => ['accent' => 'bg-amber-500', 'bg' => 'bg-amber-50 dark:bg-amber-900/20', 'badge' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'],
        'danger' => ['accent' => 'bg-red-500', 'bg' => 'bg-red-50 dark:bg-red-900/20', 'badge' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'],
        'gray' => ['accent' => 'bg-gray-400', 'bg' => 'bg-gray-50 dark:bg-gray-800', 'badge' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'],
    ];
@endphp

<x-filament-panels::page>
    <div class="grid items-start gap-4 xl:grid-cols-[minmax(240px,280px)_minmax(0,1fr)]">
        {{-- Academic Structure sidebar — faculty → department navigation, ported from the legacy app's layout --}}
        <aside class="flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 p-4 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                    {{ __('patrimoine.timetable.academic_structure') }}
                </h2>
                <p class="mt-1 text-xs text-gray-500">
                    {{ __('patrimoine.timetable.academic_structure_hint') }}
                </p>
            </div>
            <div class="max-h-[28rem] flex-1 space-y-3 overflow-y-auto p-3">
                @forelse ($this->academicStructure() as $faculty)
                    <div class="rounded-lg border border-gray-200 p-2 dark:border-gray-700">
                        <div class="mb-1.5 px-1 text-[11px] font-bold uppercase tracking-wide text-primary-600 dark:text-primary-400">
                            {{ $faculty->name }}
                        </div>
                        <div class="space-y-0.5">
                            @foreach ($faculty->departments as $department)
                                <button
                                    type="button"
                                    wire:click="selectDepartment({{ $department->id }})"
                                    @class([
                                        'w-full rounded-md border-l-2 px-2 py-1.5 text-left text-xs transition-colors',
                                        'border-l-primary-600 bg-primary-50 font-semibold text-primary-700 dark:bg-primary-900/20 dark:text-primary-300' => $this->departmentId === $department->id,
                                        'border-l-transparent text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800' => $this->departmentId !== $department->id,
                                    ])
                                >
                                    {{ $department->name }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="px-1 text-xs text-gray-400">{{ __('patrimoine.timetable.no_departments') }}</p>
                @endforelse
            </div>
        </aside>

        <div class="flex flex-col gap-4">
        <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-end justify-between gap-4">
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
                <div class="min-w-[10rem]">
                    <label for="timetable-level-filter" class="mb-1 block text-xs font-medium text-gray-500">
                        {{ __('patrimoine.timetable.filter_level') }}
                    </label>
                    <select
                        id="timetable-level-filter"
                        class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                        wire:model.live="filterLevel"
                    >
                        <option value="">{{ __('patrimoine.timetable.filter_all_levels') }}</option>
                        @foreach (ReservationLevel::cases() as $level)
                            <option value="{{ $level->value }}">{{ $level->getLabel() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[12rem]">
                    <label for="timetable-group-filter" class="mb-1 block text-xs font-medium text-gray-500">
                        {{ __('patrimoine.timetable.filter_group') }}
                    </label>
                    <input
                        id="timetable-group-filter"
                        type="text"
                        list="timetable-known-groups"
                        placeholder="{{ __('patrimoine.timetable.filter_group_placeholder') }}"
                        class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                        wire:model.live.debounce.400ms="filterStudentGroup"
                    />
                    <datalist id="timetable-known-groups">
                        @foreach ($knownStudentGroups as $group)
                            <option value="{{ $group }}"></option>
                        @endforeach
                    </datalist>
                </div>
                <div class="text-right text-xs text-gray-500">
                    @if ($currentDepartment)
                        <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $currentDepartment->faculty->name }}</span>
                        <span class="mx-1">&mdash;</span>
                        <span>{{ $currentDepartment->name }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
            {{-- Weekly grid — fixed time-slot rows × Sat–Thu columns, matching the legacy app --}}
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="w-fit min-w-full">
                    <div class="grid border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800" style="grid-template-columns: 130px repeat(6, minmax(160px, 1fr));">
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
                        <div class="grid border-b border-gray-200 dark:border-gray-700" style="grid-template-columns: 130px repeat(6, minmax(160px, 1fr));">
                            <div class="bg-white p-2 text-[11px] font-semibold text-gray-700 dark:bg-gray-900 dark:text-gray-200">
                                {{ $slot['label'] }}
                            </div>
                            @foreach (TimetableBuilder::WEEK_DAYS as $day)
                                @php
                                    $cellReservations = $slots["{$day['dow']}-{$slot['label']}"] ?? collect();
                                @endphp
                                <div class="min-h-[92px] space-y-1.5 border-l border-gray-200 bg-white p-1.5 dark:border-gray-700 dark:bg-gray-900">
                                    @forelse ($cellReservations as $reservation)
                                        @php $style = $statusClasses[$reservation->status->getColor()]; @endphp
                                        <div class="group relative overflow-hidden rounded-lg border border-gray-200 {{ $style['bg'] }} shadow-sm transition-shadow hover:shadow-md dark:border-gray-700">
                                            <div class="absolute inset-y-0 left-0 w-[3px] {{ $style['accent'] }}"></div>
                                            <div class="py-1.5 pl-2.5 pr-1.5 text-xs">
                                                <div class="mb-1 flex items-start justify-between gap-1">
                                                    <span class="font-semibold leading-snug text-gray-800 dark:text-gray-100">
                                                        {{ $reservation->module_name }}
                                                    </span>
                                                    <span class="shrink-0 rounded-full px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide {{ $style['badge'] }}">
                                                        {{ $reservation->status->getLabel() }}
                                                    </span>
                                                </div>
                                                <div class="space-y-0.5">
                                                    <div class="flex items-center gap-1 text-[10px] text-gray-500">
                                                        <x-filament::icon icon="heroicon-o-user-group" class="h-3 w-3 shrink-0" />
                                                        <span>{{ collect([$reservation->level?->getLabel(), $reservation->student_group])->filter()->implode(' · ') ?: '—' }}</span>
                                                    </div>
                                                    <div class="flex items-center gap-1 text-[10px] text-gray-500">
                                                        <x-filament::icon icon="heroicon-o-user" class="h-3 w-3 shrink-0" />
                                                        <span>{{ $reservation->teacher?->name ?? '—' }}</span>
                                                    </div>
                                                    <div class="flex items-center gap-1 text-[10px] text-gray-500">
                                                        <x-filament::icon icon="heroicon-o-map-pin" class="h-3 w-3 shrink-0" />
                                                        <span>{{ $reservation->local->code }} &middot; {{ $reservation->local->building->name }}</span>
                                                    </div>
                                                </div>
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
                                        </div>
                                    @empty
                                        <span class="text-[10px] text-gray-300">{{ __('patrimoine.timetable.empty_slot') }}</span>
                                    @endforelse
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                <div class="flex items-center gap-2 border-t border-gray-200 px-3 py-2.5 dark:border-gray-700">
                    <span class="h-2.5 w-2.5 rounded-full bg-green-600"></span>
                    <span class="text-[11px] font-medium uppercase tracking-wide text-gray-500">
                        {{ __('patrimoine.timetable.legend_confirmed') }}
                    </span>
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
        </div>
    </div>
</x-filament-panels::page>
