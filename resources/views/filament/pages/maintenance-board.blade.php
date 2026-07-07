@php
    use App\Enums\TicketStatus;

    $columns = $this->columns();
    // A class-string subject routes to a policy method taking only the
    // user (like create()); update() needs a record, so check the raw
    // permission here — this only decides the drag affordance, the actual
    // move is still authorized per-ticket in moveTicket().
    $canMove = auth()->user()?->can('Update:MaintenanceTicket') ?? false;
@endphp

<x-filament-panels::page>
    <div
        x-data="{ draggingId: null }"
        class="grid grid-cols-1 gap-4 overflow-x-auto pb-2 sm:grid-cols-2 xl:grid-cols-5"
    >
        @foreach (TicketStatus::boardColumns() as $status)
            @php $tickets = $columns[$status->value] ?? collect(); @endphp
            <div
                class="flex min-w-[260px] flex-col gap-3 rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/40"
                x-on:dragover.prevent
                x-on:drop.prevent="
                    if (draggingId) { $wire.moveTicket(draggingId, '{{ $status->value }}'); draggingId = null; }
                "
            >
                <div class="flex items-center justify-between px-1">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500">{{ $status->getLabel() }}</span>
                    <span class="rounded-full bg-gray-200 px-2 py-0.5 text-[10px] font-bold text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $tickets->count() }}</span>
                </div>

                <div class="flex flex-col gap-2">
                    @forelse ($tickets as $ticket)
                        @php
                            $subject = $ticket->equipment?->designation ?? $ticket->local?->name ?? '—';
                            $locationCode = $ticket->local?->code ?? $ticket->equipment?->local?->code;
                            $overdue = now()->greaterThanOrEqualTo($ticket->sla_due_at);
                            $technicianName = $ticket->interventions->first()?->technician?->name;
                        @endphp
                        <div
                            @if ($canMove) draggable="true" @endif
                            x-on:dragstart="draggingId = {{ $ticket->id }}"
                            x-on:dragend="draggingId = null"
                            class="rounded-lg border border-gray-200 bg-white p-2.5 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-900 {{ $canMove ? 'cursor-grab active:cursor-grabbing' : '' }}"
                        >
                            <div class="mb-1 flex items-start justify-between gap-1">
                                <span class="font-mono text-[11px] font-semibold text-primary-600 dark:text-primary-400">{{ $ticket->reference }}</span>
                                <span @class([
                                    'shrink-0 rounded-full px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide',
                                    'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => $ticket->priority->value === 'urgent',
                                    'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => $ticket->priority->value !== 'urgent',
                                ])>
                                    {{ $ticket->priority->getLabel() }}
                                </span>
                            </div>
                            <div class="font-semibold text-gray-800 dark:text-gray-100">{{ $subject }}</div>
                            @if ($locationCode)
                                <div class="text-gray-500">{{ $locationCode }}</div>
                            @endif
                            <div class="mt-1.5 flex items-center justify-between">
                                <span @class([
                                    'rounded px-1.5 py-0.5 text-[10px] font-medium',
                                    'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-300' => $overdue,
                                    'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' => ! $overdue,
                                ])>
                                    {{ $overdue ? __('patrimoine.board.overdue') : $ticket->sla_due_at->diffForHumans(['parts' => 1]) }}
                                </span>
                                @if ($technicianName)
                                    <span class="truncate text-[10px] text-gray-500">{{ $technicianName }}</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="px-1 text-[11px] italic text-gray-400">{{ __('patrimoine.board.no_tickets') }}</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
