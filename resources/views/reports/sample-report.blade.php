<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Test Report — {{ $event->event_code }}</title>
    <style>
        @page { margin: 22mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }
        .header { border-bottom: 2px solid #14532d; padding-bottom: 10px; margin-bottom: 14px; }
        .header td { vertical-align: middle; }
        .crest { font-size: 20px; font-weight: bold; color: #14532d; letter-spacing: 0.5px; }
        .subtitle { font-size: 11px; color: #444; }
        .lab { font-size: 11px; color: #444; margin-top: 2px; }
        .doc-title { text-align: center; font-size: 15px; font-weight: bold; margin: 6px 0 14px; text-transform: uppercase; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 3px 4px; }
        .meta .label { color: #555; width: 26%; }
        .meta .value { font-weight: bold; }
        .section-title { background: #f0f4f0; border-left: 3px solid #14532d; padding: 4px 6px; font-weight: bold; margin: 14px 0 6px; }
        .params { margin-top: 4px; }
        .params th { background: #14532d; color: #fff; text-align: left; padding: 5px 6px; font-size: 10px; }
        .params td { border-bottom: 1px solid #ddd; padding: 5px 6px; }
        .ok { color: #14532d; font-weight: bold; }
        .bad { color: #b91c1c; font-weight: bold; }
        .verdict { margin-top: 16px; padding: 12px; text-align: center; border-radius: 4px; }
        .verdict.fit { background: #dcfce7; border: 2px solid #14532d; }
        .verdict.unfit { background: #fee2e2; border: 2px solid #b91c1c; }
        .verdict .word { font-size: 26px; font-weight: bold; letter-spacing: 2px; }
        .verdict.fit .word { color: #14532d; }
        .verdict.unfit .word { color: #b91c1c; }
        .sign td { padding-top: 26px; width: 50%; font-size: 10px; }
        .sign .line { border-top: 1px solid #666; padding-top: 3px; width: 78%; }
        .footer { margin-top: 18px; border-top: 1px solid #ccc; padding-top: 6px; font-size: 9px; color: #666; text-align: center; }
        .additional { color: #92400e; font-style: italic; }
    </style>
</head>
<body>

<table class="header">
    <tr>
        @if (!empty($config['logo_path']) && file_exists($config['logo_path']))
            <td width="70"><img src="{{ $config['logo_path'] }}" height="56" alt=""></td>
        @endif
        <td>
            <div class="crest">{{ $config['authority_name'] }}</div>
            <div class="subtitle">{{ $config['authority_subtitle'] }}</div>
            <div class="lab">{{ $config['lab_name'] }}</div>
        </td>
        <td width="110" style="text-align: right;">
            <img src="{{ $qrDataUri }}" width="92" height="92" alt="QR">
        </td>
    </tr>
</table>

<div class="doc-title">Food Sample Test Report</div>

<table class="meta">
    <tr>
        <td class="label">Report / Event Code</td>
        <td class="value">{{ $event->event_code }}</td>
        <td class="label">Blind Code</td>
        <td class="value">{{ $part->blind_code ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Food Business</td>
        <td class="value">{{ $premises->name }}</td>
        <td class="label">License No.</td>
        <td class="value">{{ $premises->license_no }}</td>
    </tr>
    <tr>
        <td class="label">Address</td>
        <td class="value" colspan="3">{{ $premises->address }}, {{ $premises->city }}</td>
    </tr>
    <tr>
        <td class="label">Food Item</td>
        <td class="value">{{ $event->food_item }}@if ($event->food_category) ({{ $event->food_category }})@endif</td>
        <td class="label">Brand</td>
        <td class="value">{{ $event->brand_name ?: '—' }}</td>
    </tr>
    <tr>
        <td class="label">Collected On</td>
        <td class="value">{{ optional($event->collected_at)->format('d M Y, H:i') }}</td>
        <td class="label">Received On</td>
        <td class="value">{{ $receivedAt ? $receivedAt->format('d M Y, H:i') : '—' }}</td>
    </tr>
    <tr>
        <td class="label">Laboratory Section</td>
        <td class="value">{{ $labResult->lab_section->label() }}</td>
        <td class="label">Perishable</td>
        <td class="value">{{ $event->is_perishable ? 'Yes (cold chain)' : 'No' }}</td>
    </tr>
</table>

<div class="section-title">Analytical Results</div>
<table class="params">
    <thead>
    <tr>
        <th width="34%">Parameter</th>
        <th width="18%">Result</th>
        <th width="12%">Unit</th>
        <th width="22%">Permissible Limit</th>
        <th width="14%">Status</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($labResult->parameters ?? [] as $p)
        <tr>
            <td>
                {{ $p['name'] ?? '—' }}
                @if (!empty($p['is_additional']))
                    <span class="additional">(additional)</span>
                @endif
            </td>
            <td>{{ $p['value'] ?? '—' }}</td>
            <td>{{ $p['unit'] ?? '—' }}</td>
            <td>{{ $p['permissible_limit'] ?? '—' }}</td>
            <td class="{{ ($p['within_limit'] ?? false) ? 'ok' : 'bad' }}">
                {{ ($p['within_limit'] ?? false) ? 'Within limit' : 'Out of limit' }}
            </td>
        </tr>
    @empty
        <tr><td colspan="5">No parameters recorded.</td></tr>
    @endforelse
    </tbody>
</table>

@php($isFit = $labResult->verdict?->value === 'FIT')
<div class="verdict {{ $isFit ? 'fit' : 'unfit' }}">
    <div style="font-size: 10px; letter-spacing: 1px; color: #444;">CONCLUSION</div>
    <div class="word">{{ $labResult->verdict?->value }}</div>
    <div style="font-size: 11px;">{{ $labResult->verdict?->label() }}</div>
</div>

<table class="sign">
    <tr>
        <td>
            <div class="line">
                <strong>{{ $labResult->analyst?->name ?? '—' }}</strong><br>
                Analyst
            </div>
        </td>
        <td>
            <div class="line">
                <strong>{{ $labResult->verifiedBy?->name ?? '—' }}</strong><br>
                Verifying Officer<br>
                Verified: {{ optional($labResult->verdict_at)->format('d M Y, H:i') }}
            </div>
        </td>
    </tr>
</table>

<div class="footer">
    This report is system-generated by the PFA Sample Testing &amp; Tracking System and is valid without a physical signature.<br>
    Verify this report at: {{ $trackingUrl }}
</div>

</body>
</html>
