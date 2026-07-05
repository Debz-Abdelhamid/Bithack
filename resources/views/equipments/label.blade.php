<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $equipment->inventory_code }} — {{ __('patrimoine.qr.label_title') }}</title>
    <style>
        /* A4 print label — same flow as the legacy app (ui-design.md §6):
           QR + monospace inventory code, auto window.print(). */
        @page { size: A4; margin: 20mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding-top: 48px;
            display: flex;
            justify-content: center;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            color: #111c2c;
        }
        .label {
            width: 340px;
            padding: 24px;
            text-align: center;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        .label svg { width: 256px; height: 256px; }
        .code {
            margin-top: 16px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        .designation { margin-top: 4px; font-size: 13px; color: #3f4948; }
        .org {
            margin-top: 12px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #6f7979;
        }
    </style>
</head>
<body onload="window.print()">
    <div class="label">
        {{-- System-generated SVG encoding the opaque lookup URL — never a sequential id. --}}
        {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(256)->errorCorrection('M')->generate($lookupUrl) !!}
        <div class="code">{{ $equipment->inventory_code }}</div>
        <div class="designation">{{ $equipment->designation }}</div>
        <div class="org">Patrimo — UBMA</div>
    </div>
</body>
</html>
