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
        /*
    ════════════════════════════════════════════════════════════════════
    DATA CONTRACT
    ════════════════════════════════════════════════════════════════════

    $body = [
        'mode'         => 'daily',           // 'daily' | 'monthly' | 'yearly'
        'period_label' => 'March 2026',
        ── DAILY ────────────────────────────────────────────────────────
        //  groups = one item per date shown (can be multi-day range).
        //  No L1 grouping; each group renders directly with a Date column.

        ── MONTHLY ──────────────────────────────────────────────────────
        //  groups  = one item per MONTH
        //  sub_periods = Weeks within that month (Week 1 … Week 4)

        ── YEARLY ───────────────────────────────────────────────────────
        //  groups  = one item per YEAR
        //  sub_periods = Months within that year (January … December)

        'groups' => [
            [
                // ── DAILY only ──
                'label'    => '18 Mar 2026',
                'day_name' => 'Wednesday',   // optional
                'rows'     => [...],

                // ── MONTHLY & YEARLY ──
                // 'label'       => 'March 2026',   // L1 header
                // 'sub_periods' => [
                //     ['label' => 'Week 1',  'rows' => [...]],
                //     ['label' => 'Week 2',  'rows' => [...]],
                //     ['label' => 'Week 3',  'rows' => [...]],
                //     ['label' => 'Week 4',  'rows' => [...]],
                // ],

                // ── YEARLY ──
                // 'label'       => '2026',
                // 'sub_periods' => [
                //     ['label' => 'January',   'rows' => [...]],
                //     ['label' => 'February',  'rows' => [...]],
                //     ...
                //     ['label' => 'December',  'rows' => [...]],
                // ],
            ],
        ],
    ];

    ── ROW STRUCTURE (same for all modes) ──────────────────────────────
    [
        'category'     => 'umrah_packages',
        // ^ umrah_packages | leisure_package | friday_blessings_badal
        //   | wakaf_jemaah  | others
        'package_item' => '11 Days Deluxe – 16 Jun 2026',
        'ref_no'       => 'KTG-251571',
        'amount'       => 4745.00,
        'cash'         => 0.00,
        'nets'         => 0.00,
        'visa'         => 0.00,
        'master'       => 0.00,
        'paynow'       => 4745.00,
        'total_sale'   => 4745.00,
        'maker'        => 'NINA',
        'remarks'      => '50% PAYMENT',
    ]
    ════════════════════════════════════════════════════════════════════
    */

        $report = is_array($body ?? null) ? $body : [];

        if (!isset($report['mode']) && isset($report['period'])) {
            $report['mode'] = (string) $report['period'];
        }

        if (!isset($report['period_label'])) {
            $report['period_label'] =
                (string) ($report['date_range_label'] ?? ucfirst((string) ($report['period'] ?? 'daily')));
        }

        if (empty($report['groups']) && is_array($report['rows'] ?? null) && count($report['rows']) > 0) {
            $rows = collect($report['rows']);

            if (($report['mode'] ?? 'daily') === 'daily') {
                $report['groups'] = $rows
                    ->groupBy(fn(array $row) => (string) ($row['date'] ?? '-'))
                    ->map(function (\Illuminate\Support\Collection $groupRows, string $dateLabel) {
                        return [
                            'label' => $dateLabel,
                            'day_name' => null,
                            'rows' => $groupRows->values()->all(),
                        ];
                    })
                    ->values()
                    ->all();
            } elseif (($report['mode'] ?? 'daily') === 'monthly') {
                $report['groups'] = $rows
                    ->groupBy(function (array $row): string {
                        $parsed = \Carbon\Carbon::parse((string) ($row['date'] ?? now()->toDateString()));
                        return $parsed->translatedFormat('F Y');
                    })
                    ->map(function (\Illuminate\Support\Collection $monthRows, string $monthLabel) {
                        $subPeriods = $monthRows
                            ->groupBy(function (array $row): string {
                                $parsed = \Carbon\Carbon::parse((string) ($row['date'] ?? now()->toDateString()));
                                $weekNumber = (int) ceil(((int) $parsed->format('j')) / 7);
                                return 'Week ' . $weekNumber;
                            })
                            ->map(
                                fn(\Illuminate\Support\Collection $weekRows, string $weekLabel) => [
                                    'label' => $weekLabel,
                                    'rows' => $weekRows->values()->all(),
                                ],
                            )
                            ->values()
                            ->all();

                        return [
                            'label' => $monthLabel,
                            'sub_periods' => $subPeriods,
                        ];
                    })
                    ->values()
                    ->all();
            } else {
                $report['groups'] = $rows
                    ->groupBy(function (array $row): string {
                        $parsed = \Carbon\Carbon::parse((string) ($row['date'] ?? now()->toDateString()));
                        return $parsed->format('Y');
                    })
                    ->map(function (\Illuminate\Support\Collection $yearRows, string $yearLabel) {
                        $subPeriods = $yearRows
                            ->groupBy(function (array $row): string {
                                $parsed = \Carbon\Carbon::parse((string) ($row['date'] ?? now()->toDateString()));
                                return $parsed->translatedFormat('F');
                            })
                            ->map(
                                fn(\Illuminate\Support\Collection $monthRows, string $monthLabel) => [
                                    'label' => $monthLabel,
                                    'rows' => $monthRows->values()->all(),
                                ],
                            )
                            ->values()
                            ->all();

                        return [
                            'label' => $yearLabel,
                            'sub_periods' => $subPeriods,
                        ];
                    })
                    ->values()
                    ->all();
            }
        } elseif (empty($report['groups']) && is_array($report['categories'] ?? null)) {
            $normalizeCategory = function (string $label): string {
                $normalizedLabel = strtolower(trim($label));

                return match ($normalizedLabel) {
                    'umrah packages' => 'umrah_packages',
                    'leisure package' => 'leisure_package',
                    'friday blessings / badal',
                    'friday blessings/badal',
                    'friday blessings badal'
                        => 'friday_blessings_badal',
                    'wakaf jemaah' => 'wakaf_jemaah',
                    default => 'others',
                };
            };

            $report['groups'] = [
                [
                    'label' => (string) ($report['date_range_label'] ?? ($report['period_label'] ?? '-')),
                    'day_name' => null,
                    'rows' => collect($report['categories'])
                        ->map(function (array $categoryRow) use ($normalizeCategory) {
                            $amount = (float) ($categoryRow['amount'] ?? 0);
                            $categoryName = (string) ($categoryRow['category'] ?? 'Others');
                            $receiptCount = (int) ($categoryRow['receipt_count'] ?? 0);

                            return [
                                'category' => $normalizeCategory($categoryName),
                                'package_item' => $categoryName,
                                'ref_no' => '-',
                                'amount' => $amount,
                                'cash' => 0.0,
                                'nets' => 0.0,
                                'visa' => 0.0,
                                'master' => 0.0,
                                'paynow' => $amount,
                                'total_sale' => $amount,
                                'maker' => '-',
                                'remarks' => $receiptCount . ' receipt rows',
                            ];
                        })
                        ->values()
                        ->all(),
                ],
            ];

            $report['mode'] = 'daily';
        }

        $mode = $report['mode'] ?? 'daily';
        $groups = $report['groups'] ?? [];

        /* ── Sub-period scaffolding ─────────────────────────────── */
        // Monthly  → render at least Week 1 … Week 4, and include extra weeks if present (e.g., Week 5)
        // Yearly   → always render January … December
        $monthlySubLabels = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
        $yearlySubLabels = [
            'January',
            'February',
            'March',
            'April',
            'May',
            'June',
            'July',
            'August',
            'September',
            'October',
            'November',
            'December',
        ];

        /* ── Categories ─────────────────────────────────────────── */
        $categoriesFromRows = collect($report['rows'] ?? [])
            ->mapWithKeys(function (array $row) {
                $category = trim((string) ($row['category'] ?? 'Others'));
                $key = \Illuminate\Support\Str::of($category)->lower()->slug('_')->value();

                if ($key === '') {
                    return ['others' => 'Others'];
                }

                return [$key => $category];
            })
            ->all();

        $categories =
            $categoriesFromRows !== []
                ? $categoriesFromRows
                : [
                    'umrah_packages' => 'Umrah Packages',
                    'leisure_package' => 'Leisure Package',
                    'friday_blessings_badal' => 'Friday Blessings / Badal',
                    'wakaf_jemaah' => 'Wakaf Jemaah',
                    'others' => 'Others',
                ];

        $paymentMethodColumns = collect($report['payment_methods'] ?? ['cash', 'nets', 'visa', 'master', 'paynow'])
            ->map(fn($method) => strtolower(trim((string) $method)))
            ->filter(fn($method) => $method !== '' && $method !== 'amount' && $method !== 'total_sale')
            ->unique()
            ->values()
            ->all();

        if (count($paymentMethodColumns) === 0) {
            $paymentMethodColumns = ['cash', 'nets', 'visa', 'master', 'paynow'];
        }

        $fields = array_merge(['amount'], $paymentMethodColumns, ['total_sale']);
        $zero = array_fill_keys($fields, 0.0);
        $fmt = fn($v) => '$' . number_format((float) $v, 2);

        $methodHeaderLabel = function (string $method): string {
            return match (strtolower($method)) {
                'paynow' => 'Paynow',
                default => ucfirst($method),
            };
        };

        /* ── Helper: sum fields from flat rows array ─────────────── */
        $sumRows = function (array $rows) use ($fields, $zero): array {
            $t = $zero;
            foreach ($rows as $r) {
                foreach ($fields as $f) {
                    $t[$f] += (float) ($r[$f] ?? 0);
                }
            }
            return $t;
        };

        /* ── Helper: add two totals arrays ──────────────────────── */
        $addTot = function (array $a, array $b) use ($fields): array {
            foreach ($fields as $f) {
                $a[$f] += $b[$f];
            }
            return $a;
        };

        /* ── Helper: build category blocks with rowspan ──────────── */
        // rowspan(cat) = max(1, txCount) + 1 (the subtotal row)
        $buildCatBlocks = function (array $rows) use ($categories, $fields, $zero): array {
            $blocks = [];
            foreach ($categories as $catKey => $catLabel) {
                $catRows = array_values(
                    array_filter($rows, function ($r) use ($catKey): bool {
                        $rowCategory = trim((string) ($r['category'] ?? ''));
                        $normalizedRowKey = \Illuminate\Support\Str::of($rowCategory)->lower()->slug('_')->value();

                        return $normalizedRowKey === (string) $catKey;
                    }),
                );
                $catTot = $zero;
                foreach ($catRows as $r) {
                    foreach ($fields as $f) {
                        $catTot[$f] += (float) ($r[$f] ?? 0);
                    }
                }
                $blocks[] = [
                    'key' => $catKey,
                    'label' => $catLabel,
                    'rows' => $catRows,
                    'total' => $catTot,
                    'rowspan' => max(count($catRows), 1) + 1,
                ];
            }

            return array_values(array_filter($blocks, function (array $block): bool {
                return count($block['rows'] ?? []) > 0;
            }));
        };

        /* ── Helper: ensure sub_periods has every required label ─── */
        // Fills in empty-row stubs for any missing Week/Month label.
        $padSubPeriods = function (array $subPeriods, array $requiredLabels): array {
            $indexed = [];
            foreach ($subPeriods as $sp) {
                $indexed[$sp['label'] ?? ''] = $sp;
            }
            $result = [];
            foreach ($requiredLabels as $lbl) {
                $result[] = $indexed[$lbl] ?? ['label' => $lbl, 'rows' => []];
            }
            return $result;
        };

        $buildMonthlyRequiredLabels = function (array $subPeriods) use ($monthlySubLabels): array {
            $maxWeekNumber = 4;

            foreach ($subPeriods as $subPeriod) {
                $label = (string) ($subPeriod['label'] ?? '');
                if (preg_match('/week\s+(\d+)/i', $label, $matches) === 1) {
                    $weekNumber = (int) ($matches[1] ?? 0);
                    if ($weekNumber > $maxWeekNumber) {
                        $maxWeekNumber = $weekNumber;
                    }
                }
            }

            $labels = [];
            for ($week = 1; $week <= $maxWeekNumber; $week++) {
                $labels[] = 'Week ' . $week;
            }

            return !empty($labels) ? $labels : $monthlySubLabels;
        };

        /* ── Build render plan ───────────────────────────────────── */
        $plan = [];
        $overallTotal = $zero;

        if ($mode === 'daily') {
            // ── DAILY: no L1/L2 grouping ──────────────────────────
            foreach ($groups as $group) {
                $dailyDateLabel = (string) ($group['label'] ?? '-');
                $dailyDayLabel = $group['day_name'] ?? null;

                if (str_contains($dailyDateLabel, ' - ')) {
                    [$startLabel, $endLabel] = array_pad(explode(' - ', $dailyDateLabel, 2), 2, null);
                    $startLabel = trim((string) $startLabel);
                    $endLabel = trim((string) $endLabel);

                    if ($startLabel !== '' && $endLabel !== '' && $startLabel === $endLabel) {
                        $dailyDateLabel = $startLabel;
                    }
                }

                try {
                    $parsedDailyDate = \Carbon\Carbon::parse($dailyDateLabel);
                    $dailyDateLabel = $parsedDailyDate->format('d F Y');

                    if (empty($dailyDayLabel)) {
                        $dailyDayLabel = $parsedDailyDate->format('l');
                    }
                } catch (\Throwable $e) {
                    // Keep original fallback labels when parsing fails.
                }

                $rows = $group['rows'] ?? [];
                $cats = $buildCatBlocks($rows);
                $catSpanSum = array_sum(array_column($cats, 'rowspan'));
                $plan[] = [
                    'type' => 'daily',
                    'label' => $dailyDateLabel,
                    'day_name' => $dailyDayLabel,
                    'cats' => $cats,
                    'total' => $sumRows($rows),
                    'rowspan' => $catSpanSum + 1, // +1 for the grand-total row
                ];
                $overallTotal = $addTot($overallTotal, $sumRows($rows));
            }
        } else {
            // ── MONTHLY / YEARLY: two-level grouping ──────────────
            foreach ($groups as $group) {
                // Ensure every Week (monthly) or Month (yearly) is present
                $requiredLabels =
                    $mode === 'yearly' ? $yearlySubLabels : $buildMonthlyRequiredLabels($group['sub_periods'] ?? []);
                $subPeriods = $padSubPeriods($group['sub_periods'] ?? [], $requiredLabels);

                $l1Total = $zero;
                $l1Rowspan = 0;
                $subPlanList = [];

                foreach ($subPeriods as $sub) {
                    $rows = $sub['rows'] ?? [];
                    $cats = $buildCatBlocks($rows);
                    $catSpanSum = array_sum(array_column($cats, 'rowspan'));
                    $subTotal = $sumRows($rows);
                    $subRowspan = $catSpanSum + 1; // +1 for sub-period grand-total row

                    $l1Rowspan += $subRowspan;
                    $l1Total = $addTot($l1Total, $subTotal);

                    $subPlanList[] = [
                        'label' => $sub['label'],
                        'cats' => $cats,
                        'total' => $subTotal,
                        'rowspan' => $subRowspan,
                    ];
                }

                $l1Rowspan += 1; // +1 for L1 grand-total row
                $overallTotal = $addTot($overallTotal, $l1Total);

                $plan[] = [
                    'type' => 'grouped',
                    'label' => $group['label'] ?? '-',
                    'subs' => $subPlanList,
                    'total' => $l1Total,
                    'rowspan' => $l1Rowspan,
                ];
            }
        }

        $multiL1 = count($plan) > 1;
        $isDailyMode = $mode === 'daily';

        /* ── Column header labels ───────────────────────────────── */
        $col1Label = match ($mode) {
            'yearly' => 'Year',
            'monthly' => 'Month',
            default => 'Date',
        };
        $col2Label = match ($mode) {
            'yearly' => 'Month',
            'monthly' => 'Week',
            default => null,
        };
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
