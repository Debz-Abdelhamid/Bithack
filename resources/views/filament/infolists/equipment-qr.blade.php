@php
    /** @var \App\Models\Equipment $equipment */
    $equipment = $getRecord();
    $qr = $equipment->qrCode;
    $lookupUrl = $qr !== null ? route('qr.lookup', ['token' => $qr->token]) : null;
@endphp

<div class="flex flex-col items-center gap-3 py-2">
    @if ($qr !== null)
        {{-- System-generated SVG encoding our own lookup URL (opaque UUID
             token) — not user content, safe to render unescaped. --}}
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700">
            {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(256)->errorCorrection('M')->generate($lookupUrl) !!}
        </div>

        <span class="font-mono text-sm font-semibold tracking-wider text-gray-950 dark:text-white">
            {{ $equipment->inventory_code }}
        </span>

        <a href="{{ $lookupUrl }}" target="_blank" rel="noopener" class="break-all text-sm text-primary-600 hover:underline">
            {{ $lookupUrl }}
        </a>

        <span class="text-xs text-gray-500 dark:text-gray-400">
            {{ $qr->printed ? __('patrimoine.qr.printed') : __('patrimoine.qr.not_printed') }}
            · {{ __('patrimoine.qr.generated_at', ['date' => $qr->generated_at->format('Y-m-d H:i')]) }}
        </span>
    @else
        <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('patrimoine.qr.missing') }}</span>
    @endif
</div>
