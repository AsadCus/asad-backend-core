@extends('layout-report')

@section('document-title', 'Payment Summary - ' . ($body['period_label'] ?? ''))

@section('title-bar')
    PAYMENT SUMMARY REPORT
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

        /* ── Base table styles ─────────────────────────────────── */
        .summary-grid,
        .section-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
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

        .th-date-sub,
        .td-date-sub {
            white-space: nowrap;
            word-break: normal !important;
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

        /* ── L1 cell: Month (monthly) | Year (yearly) ────────── */
        .td-l1 {
            vertical-align: middle !important;
            text-align: center !important;
            font-weight: 700;
            font-size: 8px;
            background: #e8f0f7;
        }

        /* ── L2 cell: Week (monthly) | Month name (yearly) ───── */
        .td-l2 {
            vertical-align: middle !important;
            text-align: center !important;
            font-weight: 600;
            font-size: 7.5px;
            background: #f4f8fb;
        }

        /* ── Daily: date cell ────────────────────────────────── */
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

        /* ── Category merged cell ────────────────────────────── */
        .td-category {
            vertical-align: middle !important;
            background: #fafbfc;
        }

        /* ── Row variants ────────────────────────────────────── */

        /* Category subtotal */
        .row-subtotal td {
            background: #f2f6fa;
            font-weight: 700;
        }

        /* Sub-period grand total (per Week / per Month) */
        .row-subperiod-total td {
            background: #deeaf7;
            font-weight: 700;
        }

        /* L1 grand total (per Month total / per Year total) */
        .row-l1-total td {
            background: #c8dcf0;
            font-weight: 700;
            font-size: 8px;
            border-top: 1.5px solid #99b8d4;
        }

        /* Overall grand total (multi-L1 only) */
        .row-overall-total td {
            background: #a8c8e4;
            font-weight: 700;
            font-size: 8px;
            border-top: 2px solid #6699bb;
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
        // modes: daily (no L1/L2), monthly (L1=month, L2=week), yearly (L1=year, L2=month)
        $report = is_array($body ?? null) ? $body : [];

        if (!isset($report['mode']) && isset($report['period'])) {
            $report['mode'] = (string) $report['period'];
        }
        if (!isset($report['period_label'])) {
            $report['period_label'] = (string) ($report['date_range_label'] ?? ucfirst((string) ($report['period'] ?? 'daily')));
        }

        // Normalise flat rows → groups structure when the service returns rows instead of groups
        if (empty($report['groups']) && is_array($report['rows'] ?? null) && count($report['rows']) > 0) {
            $rows = collect($report['rows']);
            if (($report['mode'] ?? 'daily') === 'daily') {
                $report['groups'] = $rows
                    ->groupBy(fn(array $row) => (string) ($row['date'] ?? '-'))
                    ->map(fn($groupRows, $dateLabel) => ['label' => $dateLabel, 'day_name' => null, 'rows' => $groupRows->values()->all()])
                    ->values()->all();
            } elseif (($report['mode'] ?? 'daily') === 'monthly') {
                $report['groups'] = $rows
                    ->groupBy(fn(array $row) => \Carbon\Carbon::parse((string) ($row['date'] ?? now()))->translatedFormat('F Y'))
                    ->map(function ($monthRows, $monthLabel) {
                        $subPeriods = $monthRows
                            ->groupBy(fn(array $row) => 'Week ' . (int) ceil((int) \Carbon\Carbon::parse((string) ($row['date'] ?? now()))->format('j') / 7))
                            ->map(fn($weekRows, $weekLabel) => ['label' => $weekLabel, 'rows' => $weekRows->values()->all()])
                            ->values()->all();
                        return ['label' => $monthLabel, 'sub_periods' => $subPeriods];
                    })
                    ->values()->all();
            } else {
                $report['groups'] = $rows
                    ->groupBy(fn(array $row) => \Carbon\Carbon::parse((string) ($row['date'] ?? now()))->format('Y'))
                    ->map(function ($yearRows, $yearLabel) {
                        $subPeriods = $yearRows
                            ->groupBy(fn(array $row) => \Carbon\Carbon::parse((string) ($row['date'] ?? now()))->translatedFormat('F'))
                            ->map(fn($monthRows, $monthLabel) => ['label' => $monthLabel, 'rows' => $monthRows->values()->all()])
                            ->values()->all();
                        return ['label' => $yearLabel, 'sub_periods' => $subPeriods];
                    })
                    ->values()->all();
            }
        } elseif (empty($report['groups']) && is_array($report['categories'] ?? null)) {
            $normalizeCategory = fn(string $label) => match (strtolower(trim($label))) {
                'umrah packages'                                                    => 'umrah_packages',
                'leisure package'                                                   => 'leisure_package',
                'friday blessings / badal', 'friday blessings/badal',
                'friday blessings badal'                                            => 'friday_blessings_badal',
                'wakaf jemaah'                                                      => 'wakaf_jemaah',
                default                                                             => 'others',
            };
            $report['groups'] = [[
                'label'    => (string) ($report['date_range_label'] ?? ($report['period_label'] ?? '-')),
                'day_name' => null,
                'rows'     => collect($report['categories'])
                    ->map(function (array $cat) use ($normalizeCategory) {
                        $amount = (float) ($cat['amount'] ?? 0);
                        $name   = (string) ($cat['category'] ?? 'Others');
                        return ['category' => $normalizeCategory($name), 'package_item' => $name, 'ref_no' => '-',
                                'amount' => $amount, 'cash' => 0.0, 'nets' => 0.0, 'visa' => 0.0, 'master' => 0.0,
                                'paynow' => $amount, 'total_sale' => $amount, 'maker' => '-',
                                'remarks' => ($cat['receipt_count'] ?? 0) . ' receipt rows'];
                    })->values()->all(),
            ]];
            $report['mode'] = 'daily';
        }

        $mode   = $report['mode'] ?? 'daily';
        $groups = $report['groups'] ?? [];

        // Sub-period scaffolding: monthly → at least Week 1–4; yearly → Jan–Dec
        $monthlySubLabels = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
        $yearlySubLabels  = ['January','February','March','April','May','June',
                             'July','August','September','October','November','December'];

        $categoriesFromRows = collect($report['rows'] ?? [])
            ->mapWithKeys(function (array $row) {
                $category = trim((string) ($row['category'] ?? 'Others'));
                $key = \Illuminate\Support\Str::of($category)->lower()->slug('_')->value();
                return [$key !== '' ? $key : 'others' => $category !== '' ? $category : 'Others'];
            })->all();

        $categories = $categoriesFromRows !== [] ? $categoriesFromRows : [
            'umrah_packages'        => 'Umrah Packages',
            'leisure_package'       => 'Leisure Package',
            'friday_blessings_badal'=> 'Friday Blessings / Badal',
            'wakaf_jemaah'          => 'Wakaf Jemaah',
            'others'                => 'Others',
        ];

        $paymentMethodColumns = collect($report['payment_methods'] ?? ['cash', 'nets', 'visa', 'master', 'paynow'])
            ->map(fn($m) => strtolower(trim((string) $m)))
            ->filter(fn($m) => $m !== '' && $m !== 'amount' && $m !== 'total_sale')
            ->unique()->values()->all();
        if (count($paymentMethodColumns) === 0) {
            $paymentMethodColumns = ['cash', 'nets', 'visa', 'master', 'paynow'];
        }

        $fields = array_merge(['amount'], $paymentMethodColumns, ['total_sale']);
        $zero   = array_fill_keys($fields, 0.0);
        $fmt    = fn($v) => '$' . number_format((float) $v, 2);

        $methodHeaderLabel = fn(string $m) => match (strtolower($m)) {
            'paynow' => 'Paynow', default => ucfirst($m),
        };

        $sumRows = function (array $rows) use ($fields, $zero): array {
            $t = $zero;
            foreach ($rows as $r) { foreach ($fields as $f) { $t[$f] += (float) ($r[$f] ?? 0); } }
            return $t;
        };

        $addTot = function (array $a, array $b) use ($fields): array {
            foreach ($fields as $f) { $a[$f] += $b[$f]; }
            return $a;
        };

        // rowspan per category block = max(1, txCount) + 1 subtotal row
        $buildCatBlocks = function (array $rows) use ($categories, $fields, $zero): array {
            $blocks = [];
            foreach ($categories as $catKey => $catLabel) {
                $catRows = array_values(array_filter($rows, function ($r) use ($catKey): bool {
                    $nk = \Illuminate\Support\Str::of(trim((string) ($r['category'] ?? '')))->lower()->slug('_')->value();
                    return $nk === (string) $catKey;
                }));
                $catTot = $zero;
                foreach ($catRows as $r) { foreach ($fields as $f) { $catTot[$f] += (float) ($r[$f] ?? 0); } }
                $blocks[] = ['key' => $catKey, 'label' => $catLabel, 'rows' => $catRows,
                             'total' => $catTot, 'rowspan' => max(count($catRows), 1) + 1];
            }
            return array_values(array_filter($blocks, fn(array $b) => count($b['rows'] ?? []) > 0));
        };

        $padSubPeriods = function (array $subPeriods, array $requiredLabels): array {
            $indexed = array_column($subPeriods, null, 'label');
            return array_map(fn($lbl) => $indexed[$lbl] ?? ['label' => $lbl, 'rows' => []], $requiredLabels);
        };

        $buildMonthlyRequiredLabels = function (array $subPeriods) use ($monthlySubLabels): array {
            $max = 4;
            foreach ($subPeriods as $sp) {
                if (preg_match('/week\s+(\d+)/i', (string) ($sp['label'] ?? ''), $m) === 1) {
                    $max = max($max, (int) $m[1]);
                }
            }
            return array_map(fn($w) => 'Week ' . $w, range(1, $max)) ?: $monthlySubLabels;
        };

        $plan = [];
        $overallTotal = $zero;

        if ($mode === 'daily') {
            foreach ($groups as $group) {
                $dateLabel = (string) ($group['label'] ?? '-');
                $dayLabel  = $group['day_name'] ?? null;

                if (str_contains($dateLabel, ' - ')) {
                    [$s, $e] = array_pad(explode(' - ', $dateLabel, 2), 2, null);
                    if (trim((string) $s) !== '' && trim((string) $s) === trim((string) $e)) {
                        $dateLabel = trim((string) $s);
                    }
                }
                try {
                    $parsed    = \Carbon\Carbon::parse($dateLabel);
                    $dateLabel = $parsed->format('d F Y');
                    if (empty($dayLabel)) { $dayLabel = $parsed->format('l'); }
                } catch (\Throwable) {}

                $rows = $group['rows'] ?? [];
                $cats = $buildCatBlocks($rows);
                $plan[] = ['type' => 'daily', 'label' => $dateLabel, 'day_name' => $dayLabel,
                           'cats' => $cats, 'total' => $sumRows($rows),
                           'rowspan' => array_sum(array_column($cats, 'rowspan')) + 1];
                $overallTotal = $addTot($overallTotal, $sumRows($rows));
            }
        } else {
            foreach ($groups as $group) {
                $requiredLabels = $mode === 'yearly'
                    ? $yearlySubLabels
                    : $buildMonthlyRequiredLabels($group['sub_periods'] ?? []);
                $subPeriods = $padSubPeriods($group['sub_periods'] ?? [], $requiredLabels);

                $l1Total = $zero; $l1Rowspan = 0; $subPlanList = [];
                foreach ($subPeriods as $sub) {
                    $rows        = $sub['rows'] ?? [];
                    $cats        = $buildCatBlocks($rows);
                    $subTotal    = $sumRows($rows);
                    $subRowspan  = array_sum(array_column($cats, 'rowspan')) + 1;
                    $l1Rowspan  += $subRowspan;
                    $l1Total     = $addTot($l1Total, $subTotal);
                    $subPlanList[] = ['label' => $sub['label'], 'cats' => $cats,
                                      'total' => $subTotal, 'rowspan' => $subRowspan];
                }
                $l1Rowspan += 1;
                $overallTotal = $addTot($overallTotal, $l1Total);
                $plan[] = ['type' => 'grouped', 'label' => $group['label'] ?? '-',
                           'subs' => $subPlanList, 'total' => $l1Total, 'rowspan' => $l1Rowspan];
            }
        }

        $multiL1     = count($plan) > 1;
        $isDailyMode = $mode === 'daily';
        $col1Label   = match ($mode) { 'yearly' => 'Year', 'monthly' => 'Month', default => 'Date' };
        $col2Label   = match ($mode) { 'yearly' => 'Month', 'monthly' => 'Week', default => null };
    @endphp

    {{-- ── Report Header ─────────────────────────────────────── --}}
    @if (! $isDailyMode)
        <table class="summary-grid">
            <tr>
                <th style="width:12%;">Period</th>
                <td style="width:38%;">{{ $report['period_label'] ?? ucfirst($mode) }}</td>
                <th style="width:14%;">Date Range</th>
                <td>{{ $report['date_range_label'] ?? '-' }}</td>
            </tr>
        </table>
    @endif

    {{-- ── Main Table ─────────────────────────────────────────── --}}
    <table class="section-table">

        {{-- Column headers --}}
        @if ($isDailyMode)
            <tr>
                <th style="width:10%;">Date</th>
                <th style="width:12%;">Category</th>
                <th style="width:19%;">Package / Item</th>
                <th style="width:6%;">Ref No.</th>
                <th style="width:7%;" class="text-right">Amount</th>
                @foreach ($paymentMethodColumns as $paymentMethodColumn)
                    <th style="width:6%;" class="text-right">{{ $methodHeaderLabel($paymentMethodColumn) }}</th>
                @endforeach
                <th style="width:7%;" class="text-right">Total Sale</th>
                <th style="width:5%;">Maker</th>
                <th style="width:9%;">Remarks</th>
            </tr>
        @else
            <tr>
                <th style="width:6%;">{{ $col1Label }}</th>
                <th style="width:6%;">{{ $col2Label }}</th>
                <th style="width:12%;">Category</th>
                <th style="width:15%;">Package / Item</th>
                <th style="width:6%;">Ref No.</th>
                <th style="width:7%;" class="text-right">Amount</th>
                @foreach ($paymentMethodColumns as $paymentMethodColumn)
                    <th style="width:6%;" class="text-right">{{ $methodHeaderLabel($paymentMethodColumn) }}</th>
                @endforeach
                <th style="width:7%;" class="text-right">Total Sale</th>
                <th style="width:5%;">Maker</th>
                <th style="width:7%;">Remarks</th>
            </tr>
        @endif

        @php
            $bBottom = 'border-bottom: none !important;';
            $bTop    = 'border-top: none !important;';
            $bBoth   = 'border-top: none !important; border-bottom: none !important;';
        @endphp

        @foreach ($plan as $grp)

            {{-- ════════════════════════════════════════════════════
                 DAILY — no L1/L2, single date column
                 ════════════════════════════════════════════════════ --}}
            @if ($grp['type'] === 'daily')
                @php $dateFirst = true; @endphp

                @foreach ($grp['cats'] as $cat)
                    @php $catFirst = true; @endphp

                    @foreach ($cat['rows'] as $row)
                        <tr>
                            <td class="td-date" style="{{ $dateFirst ? $bBottom : $bBoth }}">
                                @if ($dateFirst)
                                    {{ $grp['label'] }}
                                    @if (!empty($grp['day_name']))
                                        <br><span class="day-name">{{ $grp['day_name'] }}</span>
                                    @endif
                                @endif
                            </td>
                            @php $dateFirst = false; @endphp

                            <td class="td-category" style="{{ $catFirst ? $bBottom : $bBoth }}">
                                {{ $catFirst ? $cat['label'] : '' }}
                            </td>
                            @php $catFirst = false; @endphp

                            <td>{{ $row['package_item'] ?? '-' }}</td>
                            <td>{{ $row['ref_no'] ?? '-' }}</td>
                            @foreach ($fields as $f)
                                <td class="text-right">{{ $fmt($row[$f] ?? 0) }}</td>
                            @endforeach
                            <td>{{ $row['maker'] ?? '-' }}</td>
                            <td>{{ $row['remarks'] ?? '-' }}</td>
                        </tr>
                    @endforeach

                    {{-- Category subtotal --}}
                    <tr class="row-subtotal">
                        <td class="td-date" style="{{ $bBoth }}"></td>
                        <td class="td-category" style="{{ $bTop }}"></td>
                        <td colspan="2" class="text-right">Total</td>
                        @foreach ($fields as $f)
                            <td class="text-right">{{ $fmt($cat['total'][$f]) }}</td>
                        @endforeach
                        <td colspan="2"></td>
                    </tr>
                @endforeach

                {{-- Daily grand total --}}
                <tr class="row-subperiod-total">
                    <td class="td-date" style="{{ $bTop }}"></td>
                    <td colspan="3" class="text-right">Grand Total</td>
                    @foreach ($fields as $f)
                        <td class="text-right">{{ $fmt($grp['total'][$f]) }}</td>
                    @endforeach
                    <td colspan="2"></td>
                </tr>

                {{-- ════════════════════════════════════════════════════
                 MONTHLY / YEARLY — L1 (Month/Year) + L2 (Week/Month)
                 ════════════════════════════════════════════════════ --}}
            @else
                @php $l1First = true; @endphp

                @foreach ($grp['subs'] as $sub)
                    @php $l2First = true; @endphp

                    @foreach ($sub['cats'] as $cat)
                        @php $catFirst = true; @endphp

                        @foreach ($cat['rows'] as $row)
                            <tr>
                                <td class="td-l1" style="{{ $l1First ? $bBottom : $bBoth }}">
                                    {{ $l1First ? $grp['label'] : '' }}
                                </td>
                                @php $l1First = false; @endphp

                                <td class="td-l2" style="{{ $l2First ? $bBottom : $bBoth }}">
                                    {{ $l2First ? $sub['label'] : '' }}
                                </td>
                                @php $l2First = false; @endphp

                                <td class="td-category" style="{{ $catFirst ? $bBottom : $bBoth }}">
                                    {{ $catFirst ? $cat['label'] : '' }}
                                </td>
                                @php $catFirst = false; @endphp

                                <td>{{ $row['package_item'] ?? '-' }}</td>
                                <td>{{ $row['ref_no'] ?? '-' }}</td>
                                @foreach ($fields as $f)
                                    <td class="text-right">{{ $fmt($row[$f] ?? 0) }}</td>
                                @endforeach
                                <td>{{ $row['maker'] ?? '-' }}</td>
                                <td>{{ $row['remarks'] ?? '-' }}</td>
                            </tr>
                        @endforeach

                        {{-- Category subtotal --}}
                        <tr class="row-subtotal">
                            <td class="td-l1" style="{{ $bBoth }}"></td>
                            <td class="td-l2" style="{{ $bBoth }}"></td>
                            <td class="td-category" style="{{ $bTop }}"></td>
                            <td colspan="2" class="text-right">Total</td>
                            @foreach ($fields as $f)
                                <td class="text-right">{{ $fmt($cat['total'][$f]) }}</td>
                            @endforeach
                            <td colspan="2"></td>
                        </tr>
                    @endforeach

                    {{-- Sub-period grand total (per Week / per Month) --}}
                    <tr class="row-subperiod-total">
                        <td class="td-l1" style="{{ $bBoth }}"></td>
                        <td class="td-l2" style="{{ $bTop }}"></td>
                        <td colspan="3" class="text-right">Grand Total {{ $sub['label'] }}</td>
                        @foreach ($fields as $f)
                            <td class="text-right">{{ $fmt($sub['total'][$f]) }}</td>
                        @endforeach
                        <td colspan="2"></td>
                    </tr>
                @endforeach

                {{-- L1 grand total (per Month / per Year) --}}
                <tr class="row-l1-total">
                    <td class="td-l1" style="{{ $bTop }}"></td>
                    <td colspan="4" class="text-right">Total {{ $grp['label'] }}</td>
                    @foreach ($fields as $f)
                        <td class="text-right">{{ $fmt($grp['total'][$f]) }}</td>
                    @endforeach
                    <td colspan="2"></td>
                </tr>
            @endif

        @endforeach

        {{-- Overall grand total — only when multiple L1 groups --}}
        @if ($multiL1)
            <tr class="row-overall-total">
                <td colspan="{{ $isDailyMode ? 4 : 5 }}" class="text-right">Overall Grand Total</td>
                @foreach ($fields as $f)
                    <td class="text-right">{{ $fmt($overallTotal[$f]) }}</td>
                @endforeach
                <td colspan="2"></td>
            </tr>
        @endif

    </table>

    <div class="footer-section">
        @php
            // Prepare activeNotes collection for the partial to consume
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

        {{-- Notes: always shown above footer if provided --}}
        @include('partials.report-notes')

        {{-- Module footer text from Report Template Settings --}}
        @if (!empty($branding['footer_text']))
            <div class="footer-note">{!! nl2br(e($branding['footer_text'])) !!}</div>
        @endif

        @include('partials.report-signature-stamp')
    </div>
@endsection
