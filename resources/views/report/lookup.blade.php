<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $equipment->designation }} — Patrimo UBMA</title>
    <link rel="icon" href="{{ asset('logo-UBMA.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Mobile-first QR landing card (ui-design.md §2/§8) — design tokens
           from §3: slate-50 page, white card, squared radii, teal wordmark. */
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background: #f8fafc;
            color: #111c2c;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            font-size: 14px;
            line-height: 20px;
        }
        .card {
            width: 100%;
            max-width: 24rem;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        }
        .wordmark { font-size: 20px; font-weight: 700; color: #006666; }
        .wordmark span { color: #0d9488; }
        .subtitle {
            margin-top: 2px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #64748b;
        }
        h1 { margin: 20px 0 4px; font-size: 20px; line-height: 28px; font-weight: 600; letter-spacing: -0.02em; }
        .code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 13px;
            font-weight: 500;
            color: #0f766e;
        }
        .badge {
            display: inline-block;
            margin-top: 12px;
            padding: 2px 8px;
            border-radius: 2px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .badge-in_service { background: #f0fdfa; color: #0f766e; }
        .badge-under_repair { background: #fffbeb; color: #b45309; }
        .badge-decommissioned { background: #fef2f2; color: #dc2626; }
        .badge-lost { background: #ffdad6; color: #93000a; }
        dl { margin: 20px 0 0; }
        dt {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #64748b;
        }
        dd { margin: 2px 0 12px; }
        .hint {
            margin: 20px 0 0;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
            font-size: 13px;
            line-height: 18px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="wordmark">Patri<span>mo</span></div>
        <div class="subtitle">{{ __('patrimoine.brand_subtitle') }}</div>

        <h1>{{ $equipment->designation }}</h1>
        <div class="code">{{ $equipment->inventory_code }}</div>
        <div>
            <span class="badge badge-{{ $equipment->status->value }}">{{ $equipment->status->getLabel() }}</span>
        </div>

        <dl>
            <dt>{{ __('patrimoine.fields.category') }}</dt>
            <dd>{{ $equipment->category }}</dd>

            <dt>{{ __('patrimoine.fields.local') }}</dt>
            <dd>
                @if ($equipment->local !== null)
                    {{ $equipment->local->code }} — {{ $equipment->local->name }}
                    @if ($equipment->local->building !== null)
                        <br>{{ $equipment->local->building->name }}
                    @endif
                @else
                    {{ __('patrimoine.fields.unplaced') }}
                @endif
            </dd>
        </dl>

        <p class="hint">{{ __('patrimoine.lookup.report_hint') }}</p>
    </main>
</body>
</html>
