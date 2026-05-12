@extends('layout-report')

@section('document-title', 'Closing Report - ' . ($body['period_label'] ?? ''))

@section('title-bar')
    CLOSING REPORT
@endsection

@section('body-class', 'is-landscape')

@push('styles')
    @php
        $marginPreset = $branding['page_margin_preset'] ?? 'normal';
        $resolvedMargin = [
            'narrow' => ['top' => '0.56cm', 'right' => '0.50cm', 'bottom' => '0.56cm', 'left' => '0.50cm'],
            'normal' => ['top' => '0.85cm', 'right' => '0.75cm', 'bottom' => '0.85cm', 'left' => '0.75cm'],
            'wide' => ['top' => '1.70cm', 'right' => '1.50cm', 'bottom' => '1.70cm', 'left' => '1.50cm'],
        ][$marginPreset] ?? ['top' => '0.85cm', 'right' => '0.75cm', 'bottom' => '0.85cm', 'left' => '0.75cm'];

        $sectionSpacingPreset = $branding['section_spacing_preset'] ?? 'normal';
        $moduleSpacing = [
            'compact' => ['block' => '8px', 'table' => '6px', 'header' => '8px'],
            'normal' => ['block' => '10px', 'table' => '8px', 'header' => '10px'],
            'relaxed' => ['block' => '16px', 'table' => '12px', 'header' => '16px'],
        ][$sectionSpacingPreset] ?? ['block' => '10px', 'table' => '8px', 'header' => '10px'];
    @endphp
    <style>
        @page {
            size: A4 landscape;
            margin-top: {{ $resolvedMargin['top'] }};
            margin-right: {{ $resolvedMargin['right'] }};
            margin-bottom: {{ $resolvedMargin['bottom'] }};
            margin-left: {{ $resolvedMargin['left'] }};
        }

        .summary-grid,
        .section-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: {{ $moduleSpacing['block'] }};
            page-break-inside: auto;
        }

        .summary-grid th,
        .summary-grid td,
        .section-table th,
        .section-table td {
            border: 1px solid #d7dde3;
            padding: 4px 5px;
            font-size: 7.5px;
            vertical-align: middle;
            text-align: left;
            word-break: break-word;
        }

        .summary-grid th,
        .section-table th {
            background: #f4f8fb;
            font-weight: 700;
        }

        .summary-grid thead,
        .section-table thead {
            display: table-header-group;
        }

        .summary-grid tr,
        .section-table tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        .text-center {
            text-align: center !important;
        }

        .text-right {
            text-align: right !important;
        }

        /* Week label cell */
        .td-l2 {
            vertical-align: middle !important;
            text-align: center !important;
            font-size: 7.5px;
            background: #f4f8fb;
        }

        /* Daily: date cell */
        .td-date {
            vertical-align: middle !important;
            text-align: center !important;
            font-weight: 700;
            background: #f4f8fb;
        }

        .td-date .day-name {
            display: block;
            font-weight: 400;
            font-size: 6.5px;
            color: #6b7c8d;
            margin-top: 2px;
        }

        /* Row variants */
        .row-subperiod-total td {
            background: #deeaf7;
            font-weight: 700;
        }

        .row-l1-total td {
            background: #c8dcf0;
            font-weight: 700;
            font-size: 8px;
            border-top: 1.5px solid #99b8d4;
        }

        .row-empty td {
            color: #b0bec5;
        }

        .footer-section {
            margin-top: {{ $moduleSpacing['block'] }};
            font-size: 11px;
        }

        .footer-note {
            margin-bottom: {{ $moduleSpacing['table'] }};
        }
    </style>
@endpush

@section('report-content')
    @php
        $report       = is_array($body ?? null) ? $body : [];
        $categories   = is_array($report['categories'] ?? null) ? $report['categories'] : [];
        $paymentMethods = is_array($report['payment_methods'] ?? null)
            ? $report['payment_methods']
            : ['cash', 'nets', 'visa', 'master', 'paynow'];
        $rows         = is_array($report['rows'] ?? null) ? $report['rows'] : [];
        $packageInfo  = $report['package'] ?? null;

        $fmt      = fn($v) => '$' . number_format((float) $v, 2);
        $catKeys  = array_keys($categories);
        $methodKeys = $paymentMethods;
        $allKeys  = array_merge($catKeys, $methodKeys, ['total_sales']);

        $sumRows = function (array $subset) use ($allKeys): array {
            $t = array_fill_keys($allKeys, 0.0);
            foreach ($subset as $r) {
                foreach ($allKeys as $k) { $t[$k] += (float) ($r[$k] ?? 0); }
            }
            return $t;
        };

        // Group rows by month+week. Composite key (Y-m-WN) keeps April Week 1
        // and May Week 1 as separate buckets in multi-month ranges.
        $rangeStart = \Carbon\Carbon::parse($report['start_date'] ?? now()->startOfMonth());
        $rangeEnd   = \Carbon\Carbon::parse($report['end_date']   ?? now()->endOfMonth());

        $rowIndex = [];
        foreach ($rows as $r) { $rowIndex[$r['date_sort']] = $r; }

        $weekGroups = [];
        $cursor = $rangeStart->copy();
        while ($cursor->lte($rangeEnd)) {
            $dateKey  = $cursor->format('Y-m-d');
            $weekNum  = (int) ceil((int) $cursor->format('j') / 7);
            $groupKey = $cursor->format('Y-m') . '-W' . $weekNum;
            $weekLabel = $cursor->translatedFormat('F Y') . ' – Week ' . $weekNum;

            if (!isset($weekGroups[$groupKey])) {
                $weekGroups[$groupKey] = ['label' => $weekLabel, 'rows' => []];
            }

            if (isset($rowIndex[$dateKey])) {
                $weekGroups[$groupKey]['rows'][] = $rowIndex[$dateKey];
            } else {
                $stub = ['date_sort' => $dateKey, 'date' => $cursor->translatedFormat('d F Y'),
                         'day_name' => $cursor->translatedFormat('l'), 'total_sales' => 0.0, '__empty' => true];
                foreach (array_merge($catKeys, $methodKeys) as $k) { $stub[$k] = 0.0; }
                $weekGroups[$groupKey]['rows'][] = $stub;
            }

            $cursor->addDay();
        }

        $plan = [];
        $grandTotal = array_fill_keys($allKeys, 0.0);
        foreach ($weekGroups as $groupData) {
            $weekTotal = $sumRows($groupData['rows']);
            foreach (array_keys($grandTotal) as $k) { $grandTotal[$k] += $weekTotal[$k]; }
            $plan[] = ['label' => $groupData['label'], 'rows' => $groupData['rows'], 'total' => $weekTotal];
        }

        $methodHeaderLabel = fn(string $m) => match (strtolower($m)) {
            'paynow' => 'PayNow', 'nets' => 'Nets', 'visa' => 'Visa',
            'master' => 'Master', 'cash' => 'Cash',
            default  => ucwords(str_replace('_', ' ', $m)),
        };
    @endphp

    {{-- ── Report meta header ──────────────────────────────────── --}}
    <table class="summary-grid">
        <tr>
            <th style="width:10%;">Period</th>
            <td style="width:20%;">{{ $report['period_label'] ?? ucfirst($mode) }}</td>
            <th style="width:10%;">Date Range</th>
            <td style="width:20%;">{{ $report['date_range_label'] ?? '-' }}</td>
            <th style="width:10%;">Package</th>
            <td style="width:30%;">
                @if ($packageInfo)
                    {{ $packageInfo['package_number'] }} &ndash; {{ $packageInfo['name'] }}
                @else
                    -
                @endif
            </td>
        </tr>
    </table>

    {{-- ── Main table ──────────────────────────────────────────── --}}
    <table class="section-table">

        {{-- Column headers --}}
        <thead>
            <tr>
                <th class="text-center" style="width:10%;">Date</th>
                @foreach ($categories as $catKey => $catLabel)
                    <th class="text-center" style="width:fit-content;">
                        {{ $catLabel }}
                    </th>
                @endforeach
                @foreach ($paymentMethods as $method)
                    <th class="text-center" style="width:fit-content;">
                        Total {{ $methodHeaderLabel($method) }}
                    </th>
                @endforeach
                <th class="text-center" style="width:fit-content;">Total Sales</th>
            </tr>
        </thead>

        <tbody>

            @foreach ($plan as $weekGroup)
                @foreach ($weekGroup['rows'] as $row)
                    @php $isEmpty = !empty($row['__empty']); @endphp
                    @if (!$isEmpty)
                    <tr>
                        <td class="td-date">
                            {{ $row['date'] }}
                            <span class="day-name">{{ $row['day_name'] }}</span>
                        </td>
                        @foreach ($catKeys as $ck)
                            <td class="text-right">{{ $fmt($row[$ck] ?? 0) }}</td>
                        @endforeach
                        @foreach ($methodKeys as $mk)
                            <td class="text-right">{{ $fmt($row[$mk] ?? 0) }}</td>
                        @endforeach
                        <td class="text-right">{{ $fmt($row['total_sales'] ?? 0) }}</td>
                    </tr>
                    @endif
                @endforeach

                {{-- Week subtotal --}}
                <tr class="row-subperiod-total">
                    <td class="text-center">{{ $weekGroup['label'] }}</td>
                    @foreach ($catKeys as $ck)
                        <td class="text-right">{{ $fmt($weekGroup['total'][$ck] ?? 0) }}</td>
                    @endforeach
                    @foreach ($methodKeys as $mk)
                        <td class="text-right">{{ $fmt($weekGroup['total'][$mk] ?? 0) }}</td>
                    @endforeach
                    <td class="text-right">{{ $fmt($weekGroup['total']['total_sales'] ?? 0) }}</td>
                </tr>
            @endforeach

            {{-- ── Grand Total row ─────────────────────────────────── --}}
            <tr class="row-l1-total">
                <td class="text-right" style="border-top: none !important;">Grand Total</td>
                @foreach ($catKeys as $ck)
                    <td class="text-right">{{ $fmt($grandTotal[$ck] ?? 0) }}</td>
                @endforeach
                @foreach ($methodKeys as $mk)
                    <td class="text-right">{{ $fmt($grandTotal[$mk] ?? 0) }}</td>
                @endforeach
                <td class="text-right">{{ $fmt($grandTotal['total_sales'] ?? 0) }}</td>
            </tr>

        </tbody>
    </table>

    <div class="footer-section">
        @php
            $rawNotes = $report['notes'] ?? null;
            if (is_string($rawNotes) && trim($rawNotes) !== '') {
                $activeNotes = collect([['description' => nl2br(e($rawNotes))]]);
            } elseif (is_array($rawNotes)) {
                $activeNotes = collect($rawNotes)
                    ->filter(fn($n) => !empty(trim(strip_tags($n['description'] ?? ''))))
                    ->sortBy('sort_order')
                    ->values();
            } else {
                $activeNotes = collect();
            }
        @endphp

        @include('partials.report-notes')

        @if (!empty($branding['footer_text']))
            <div class="footer-note">{!! nl2br(e($branding['footer_text'])) !!}</div>
        @endif

        @include('partials.report-signature-stamp')
    </div>
@endsection
