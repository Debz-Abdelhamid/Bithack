<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $local->code }} — {{ __('patrimoine.locals.equipment_list_title') }}</title>
    <style>
        @page { size: A4; margin: 18mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            color: #111c2c;
            font-size: 13px;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            border-bottom: 2px solid #004c4c;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .org { font-size: 11px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #6f7979; }
        h1 { margin: 4px 0 0; font-size: 20px; letter-spacing: -0.01em; }
        .subtitle { margin-top: 2px; font-size: 12px; color: #3f4948; }
        .count {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 13px;
            font-weight: 600;
            color: #0f766e;
            text-align: right;
        }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th {
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6f7979;
            border-bottom: 1px solid #e2e8f0;
            padding: 6px 8px;
        }
        td { padding: 7px 8px; border-bottom: 1px solid #f1f5f4; }
        td.code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; color: #0f766e; }
        .empty { padding: 24px 8px; text-align: center; color: #6f7979; }
        footer { margin-top: 24px; font-size: 10px; color: #6f7979; text-align: right; }
    </style>
</head>
<body onload="window.print()">
    <header>
        <div>
            <div class="org">Patrimo — UBMA</div>
            <h1>{{ $local->code }} — {{ $local->name }}</h1>
            <div class="subtitle">{{ $local->building->name }}{{ $local->building->campus ? ' · '.$local->building->campus : '' }}</div>
        </div>
        <div class="count">{{ __('patrimoine.locals.equipment_count', ['count' => $equipments->count()]) }}</div>
    </header>

    @if ($equipments->isEmpty())
        <p class="empty">{{ __('patrimoine.locals.no_equipment') }}</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>{{ __('patrimoine.fields.inventory_code') }}</th>
                    <th>{{ __('patrimoine.fields.designation') }}</th>
                    <th>{{ __('patrimoine.fields.category') }}</th>
                    <th>{{ __('patrimoine.fields.condition') }}</th>
                    <th>{{ __('patrimoine.fields.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($equipments as $equipment)
                    <tr>
                        <td class="code">{{ $equipment->inventory_code }}</td>
                        <td>{{ $equipment->designation }}</td>
                        <td>{{ $equipment->category }}</td>
                        <td>{{ $equipment->condition->getLabel() }}</td>
                        <td>{{ $equipment->status->getLabel() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <footer>{{ __('patrimoine.locals.printed_on', ['date' => now()->format('Y-m-d H:i')]) }}</footer>
</body>
</html>
