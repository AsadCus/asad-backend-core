@extends('layout-report')

@section('document-title', 'Payment Summary - ' . ($body['period_label'] ?? ''))

@section('title-bar')
    PAYMENT SUMMARY REPORT
@endsection

@section('body-class', 'is-landscape')

@push('styles')
    <style>
        @page {
            size: A4 landscape;
            margin: 0.15cm 0.25cm;
        }

        /* ── Base table styles ─────────────────────────────────── */
        .summary-grid,
        .section-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 6px;
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
            margin-top: 8px;
            font-size: 11px;
        }

        .footer-note {
            margin-bottom: 6px;
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
        'generated_at' => '26 Mar 2026, 09:15',
        'generated_by' => 'Admin User',

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

        if (!isset($report['generated_at'])) {
            $report['generated_at'] = now()->translatedFormat('d M Y, H:i');
        }

        if (!isset($report['generated_by'])) {
            $report['generated_by'] = auth()->user()?->name ?? 'System';
        }

        if (empty($report['groups']) && is_array($report['rows'] ?? null) && count($report['rows']) > 0) {
            $rows = collect($report['rows']);

            if (($report['mode'] ?? 'daily') === 'daily') {
                $report['groups'] = $rows
                    ->groupBy(fn(array $row) => (string) ($row['date'] ?? '-'))
                    ->map(function (Collection $groupRows, string $dateLabel) {
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
                    ->map(function (Collection $monthRows, string $monthLabel) {
                        $subPeriods = $monthRows
                            ->groupBy(function (array $row): string {
                                $parsed = \Carbon\Carbon::parse((string) ($row['date'] ?? now()->toDateString()));
                                $weekNumber = (int) ceil(((int) $parsed->format('j')) / 7);
                                return 'Week '.$weekNumber;
                            })
                            ->map(fn (Collection $weekRows, string $weekLabel) => [
                                'label' => $weekLabel,
                                'rows' => $weekRows->values()->all(),
                            ])
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
                    ->map(function (Collection $yearRows, string $yearLabel) {
                        $subPeriods = $yearRows
                            ->groupBy(function (array $row): string {
                                $parsed = \Carbon\Carbon::parse((string) ($row['date'] ?? now()->toDateString()));
                                return $parsed->translatedFormat('F');
                            })
                            ->map(fn (Collection $monthRows, string $monthLabel) => [
                                'label' => $monthLabel,
                                'rows' => $monthRows->values()->all(),
                            ])
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
        $categoriesFromRows = collect($report['rows'] ?? [])->mapWithKeys(function (array $row) {
            $category = trim((string) ($row['category'] ?? 'Others'));
            $key = \Illuminate\Support\Str::of($category)->lower()->slug('_')->value();

            if ($key === '') {
                return ['others' => 'Others'];
            }

            return [$key => $category];
        })->all();

        $categories = $categoriesFromRows !== []
            ? $categoriesFromRows
            : [
                'umrah_packages' => 'Umrah Packages',
                'leisure_package' => 'Leisure Package',
                'friday_blessings_badal' => 'Friday Blessings / Badal',
                'wakaf_jemaah' => 'Wakaf Jemaah',
                'others' => 'Others',
            ];

        $fields = ['amount', 'cash', 'nets', 'visa', 'master', 'paynow', 'total_sale'];
        $zero = array_fill_keys($fields, 0.0);
        $fmt = fn($v) => '$' . number_format((float) $v, 2);

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
                $catRows = array_values(array_filter($rows, function ($r) use ($catKey): bool {
                    $rowCategory = trim((string) ($r['category'] ?? ''));
                    $normalizedRowKey = \Illuminate\Support\Str::of($rowCategory)->lower()->slug('_')->value();

                    return $normalizedRowKey === (string) $catKey;
                }));
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
            return $blocks;
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
                $rows = $group['rows'] ?? [];
                $cats = $buildCatBlocks($rows);
                $catSpanSum = array_sum(array_column($cats, 'rowspan'));
                $plan[] = [
                    'type' => 'daily',
                    'label' => $group['label'] ?? '-',
                    'day_name' => $group['day_name'] ?? null,
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
    <table class="summary-grid">
        <tr>
            <th style="width:10%;">Period</th>
            <td style="width:24%;">{{ $report['period_label'] ?? ucfirst($mode) }}</td>
            <th style="width:8%;">Generated</th>
            <td style="width:22%;">{{ $report['generated_at'] ?? now()->format('d M Y, H:i') }}</td>
            <th style="width:4%;">By</th>
            <td>{{ $report['generated_by'] ?? '-' }}</td>
        </tr>
    </table>

    {{-- ── Main Table ─────────────────────────────────────────── --}}
    <table class="section-table">

        {{-- Column headers --}}
        <tr>
            <th style="width:6%;">{{ $col1Label }}</th>
            @if (!$isDailyMode)
                <th style="width:6%;">{{ $col2Label }}</th>
            @endif
            <th style="width:12%;">Category</th>
            <th style="width:{{ $isDailyMode ? '17%' : '15%' }};">Package / Item</th>
            <th style="width:6%;">Ref No.</th>
            <th style="width:7%;" class="text-right">Amount</th>
            <th style="width:6%;" class="text-right">Cash</th>
            <th style="width:6%;" class="text-right">Nets</th>
            <th style="width:6%;" class="text-right">Visa</th>
            <th style="width:6%;" class="text-right">Master</th>
            <th style="width:7%;" class="text-right">Paynow</th>
            <th style="width:7%;" class="text-right">Total Sale</th>
            <th style="width:5%;">Maker</th>
            <th style="width:{{ $isDailyMode ? '9%' : '7%' }};">Remarks</th>
        </tr>

        @foreach ($plan as $grp)

            {{-- ════════════════════════════════════════════════════
                 DAILY — no L1/L2, single date column
                 ════════════════════════════════════════════════════ --}}
            @if ($grp['type'] === 'daily')
                @php $dateRendered = false; @endphp

                @foreach ($grp['cats'] as $cat)
                    @php $catRendered = false; @endphp

                    @if (count($cat['rows']) === 0)
                        {{-- Empty category: one placeholder row --}}
                        <tr>
                            @if (!$dateRendered)
                                <td class="td-date" rowspan="{{ $grp['rowspan'] }}">
                                    {{ $grp['label'] }}
                                    @if ($grp['day_name'])
                                        <span class="day-name">{{ $grp['day_name'] }}</span>
                                    @endif
                                </td>
                                @php $dateRendered = true; @endphp
                            @endif
                            <td class="td-category" rowspan="{{ $cat['rowspan'] }}">{{ $cat['label'] }}</td>
                            @php $catRendered = true; @endphp
                            <td>-</td>
                            <td>-</td>
                            @foreach ($fields as $f)
                                <td class="text-right">{{ $fmt(0) }}</td>
                            @endforeach
                            <td>-</td>
                            <td>-</td>
                        </tr>
                    @else
                        @foreach ($cat['rows'] as $row)
                            <tr>
                                @if (!$dateRendered)
                                    <td class="td-date" rowspan="{{ $grp['rowspan'] }}">
                                        {{ $grp['label'] }}
                                        @if ($grp['day_name'])
                                            <span class="day-name">{{ $grp['day_name'] }}</span>
                                        @endif
                                    </td>
                                    @php $dateRendered = true; @endphp
                                @endif
                                @if (!$catRendered)
                                    <td class="td-category" rowspan="{{ $cat['rowspan'] }}">{{ $cat['label'] }}</td>
                                    @php $catRendered = true; @endphp
                                @endif
                                <td>{{ $row['package_item'] ?? '-' }}</td>
                                <td>{{ $row['ref_no'] ?? '-' }}</td>
                                @foreach ($fields as $f)
                                    <td class="text-right">{{ $fmt($row[$f] ?? 0) }}</td>
                                @endforeach
                                <td>{{ $row['maker'] ?? '-' }}</td>
                                <td>{{ $row['remarks'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    @endif

                    {{-- Category subtotal --}}
                    <tr class="row-subtotal">
                        <td colspan="2" class="text-right">Total</td>
                        @foreach ($fields as $f)
                            <td class="text-right">{{ $fmt($cat['total'][$f]) }}</td>
                        @endforeach
                        <td colspan="2"></td>
                    </tr>
                @endforeach

                {{-- Daily grand total --}}
                <tr class="row-subperiod-total">
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
                @php $l1Rendered = false; @endphp

                @foreach ($grp['subs'] as $sub)
                    @php $l2Rendered = false; @endphp

                    @foreach ($sub['cats'] as $cat)
                        @php $catRendered = false; @endphp

                        @if (count($cat['rows']) === 0)
                            {{-- Empty category: one placeholder row --}}
                            <tr>
                                @if (!$l1Rendered)
                                    <td class="td-l1" rowspan="{{ $grp['rowspan'] }}">{{ $grp['label'] }}</td>
                                    @php $l1Rendered = true; @endphp
                                @endif
                                @if (!$l2Rendered)
                                    <td class="td-l2" rowspan="{{ $sub['rowspan'] }}">{{ $sub['label'] }}</td>
                                    @php $l2Rendered = true; @endphp
                                @endif
                                <td class="td-category" rowspan="{{ $cat['rowspan'] }}">{{ $cat['label'] }}</td>
                                @php $catRendered = true; @endphp
                                <td>-</td>
                                <td>-</td>
                                @foreach ($fields as $f)
                                    <td class="text-right">{{ $fmt(0) }}</td>
                                @endforeach
                                <td>-</td>
                                <td>-</td>
                            </tr>
                        @else
                            @foreach ($cat['rows'] as $row)
                                <tr>
                                    @if (!$l1Rendered)
                                        <td class="td-l1" rowspan="{{ $grp['rowspan'] }}">{{ $grp['label'] }}</td>
                                        @php $l1Rendered = true; @endphp
                                    @endif
                                    @if (!$l2Rendered)
                                        <td class="td-l2" rowspan="{{ $sub['rowspan'] }}">{{ $sub['label'] }}</td>
                                        @php $l2Rendered = true; @endphp
                                    @endif
                                    @if (!$catRendered)
                                        <td class="td-category" rowspan="{{ $cat['rowspan'] }}">{{ $cat['label'] }}</td>
                                        @php $catRendered = true; @endphp
                                    @endif
                                    <td>{{ $row['package_item'] ?? '-' }}</td>
                                    <td>{{ $row['ref_no'] ?? '-' }}</td>
                                    @foreach ($fields as $f)
                                        <td class="text-right">{{ $fmt($row[$f] ?? 0) }}</td>
                                    @endforeach
                                    <td>{{ $row['maker'] ?? '-' }}</td>
                                    <td>{{ $row['remarks'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        @endif

                        {{-- Category subtotal --}}
                        <tr class="row-subtotal">
                            <td colspan="2" class="text-right">Total</td>
                            @foreach ($fields as $f)
                                <td class="text-right">{{ $fmt($cat['total'][$f]) }}</td>
                            @endforeach
                            <td colspan="2"></td>
                        </tr>
                    @endforeach

                    {{-- Sub-period grand total (per Week / per Month) --}}
                    <tr class="row-subperiod-total">
                        <td colspan="3" class="text-right">Grand Total {{ $sub['label'] }}</td>
                        @foreach ($fields as $f)
                            <td class="text-right">{{ $fmt($sub['total'][$f]) }}</td>
                        @endforeach
                        <td colspan="2"></td>
                    </tr>
                @endforeach

                {{-- L1 grand total (per Month / per Year) --}}
                <tr class="row-l1-total">
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
        @if (!empty($branding['footer_text']))
            <div class="footer-note">{!! nl2br(e($branding['footer_text'])) !!}</div>
        @endif
        @include('partials.report-signature-stamp')
    </div>
@endsection
