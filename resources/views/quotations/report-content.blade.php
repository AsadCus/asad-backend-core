@extends('layout-report')

@section('document-title', 'Quotation')

@section('extra-company-reg')
    @if ($data['sales_registration_number'] ?? false)
        REGISTRATION NO. {{ $data['sales_registration_number'] }}&nbsp;&nbsp;
    @endif
@endsection

@section('title-bar')
    QUOTATION
@endsection


@push('styles')
    @php
        $sectionSpacingPreset = $branding['section_spacing_preset'] ?? 'normal';
        $moduleSpacing = [
            'compact' => ['divider' => '8px', 'block' => '8px', 'table' => '6px', 'payment_gap' => '10px'],
            'normal' => ['divider' => '10px', 'block' => '12px', 'table' => '8px', 'payment_gap' => '14px'],
            'relaxed' => ['divider' => '16px', 'block' => '18px', 'table' => '12px', 'payment_gap' => '22px'],
        ][$sectionSpacingPreset] ?? ['divider' => '10px', 'block' => '12px', 'table' => '8px', 'payment_gap' => '14px'];
    @endphp
    <style>
        /* ── Divider ── */
        .section-divider {
            border: none;
            border-top: 1px solid #d0d0d0;
            margin: {{ $moduleSpacing['divider'] }} 0;
        }

        /* ── Order Info ── */
        .order-info-section {
            padding: 0;
            margin-bottom: {{ $moduleSpacing['block'] }};
        }

        .order-info {
            width: 100%;
            border-collapse: collapse;
        }

        .order-info td {
            vertical-align: top;
            padding: 1px 0;
            font-size: 11px;
        }

        .order-info .lbl {
            white-space: nowrap;
            font-weight: bold;
            width: 90px;
        }

        .order-info .sep {
            width: 12px;
        }

        /* ── Content Wrapper ── */
        .content-wrapper {
            padding: 0;
        }

        /* ── Items Table ── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: {{ $moduleSpacing['table'] }};
        }

        .items-table-wrap {
            border-top: 1.5px solid #333;
            border-bottom: 1.5px solid #333;
        }

        .items-table td {
            padding: 3px 0;
            vertical-align: top;
            font-size: 11px;
        }

        .col-desc {
            width: auto;
        }

        .col-price {
            width: 90px;
            text-align: right;
            white-space: nowrap;
        }

        /* Sub-item indent */
        .sub-item .col-desc {
            padding-left: 20px;
        }

        /* Header rows (section headers within items) */
        .header-row td {
            font-weight: bold;
            padding: 4px 0 2px;
            border-bottom: 1px solid #ccc;
            font-size: 11px;
        }

        .item-row.root .col-desc {
            font-weight: bold;
        }

        /* ── Totals ── */
        .totals-wrapper {
            width: 100%;
            text-align: right;
            padding: 4px 0 2px;
            margin-top: {{ $moduleSpacing['table'] }};
        }

        .totals-table {
            display: inline-table;
            border-collapse: collapse;
            table-layout: auto;
        }

        .totals-table td {
            padding: 1.5px 0;
        }

        .total-label {
            width: 56%;
            text-align: right;
            font-size: 11px;
            color: #555;
            padding-right: 10px;
        }

        .total-amount {
            width: 44%;
            text-align: right;
            font-size: 12px;
            white-space: nowrap;
        }

        .total-amount.payment-info-head,
        .total-label.payment-info-head {
            color: #000;
            font-weight: bold;
        }

        .totals-table .total-row-grand td {
            border-top: 1px solid #cfcfcf;
            padding-top: 4px;
        }

        .totals-table .total-row-grand .total-label {
            font-weight: bold;
        }

        .payment-history-heading {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #000;
            text-align: right;
            padding-top: 2px;
            padding-bottom: 1px;
        }

        .payment-history-wrapper {
            margin-top: {{ $moduleSpacing['payment_gap'] }};
            padding-top: {{ $moduleSpacing['table'] }};
        }

        .payment-history-table {
            display: inline-table;
            border-collapse: collapse;
            table-layout: auto;
        }

        .payment-history-table td {
            padding: 0;
        }

        .payment-history-table .total-label {
            width: 56%;
            padding-right: 10px;
            word-break: break-word;
        }

        .payment-history-table .total-amount {
            width: 44%;
            text-align: right;
            white-space: nowrap;
        }

        /* ── Footer ── */
        .footer-section {
            padding: {{ $moduleSpacing['block'] }} 0 0;
            font-size: 10px;
            border-top: none;
        }

        .footer-note {
            text-align: right;
            margin-bottom: 8px;
            line-height: 1.5;
            color: #333;
        }

        .stamp-sig-row {
            width: 100%;
            border-collapse: collapse;
            margin-top: {{ $moduleSpacing['payment_gap'] }};
        }

        .stamp-sig-row td {
            vertical-align: bottom;
        }
    </style>
@endpush

@section('report-content')

    @php
        $rootCounter = 0;
        $childCounters = [];
        $subtotal = 0;

        if (!function_exists('alphabetIndex')) {
            function alphabetIndex($i)
            {
                $alphabet = 'abcdefghijklmnopqrstuvwxyz';
                return $i < 26 ? $alphabet[$i] : $alphabet[intdiv($i, 26) - 1] . $alphabet[$i % 26];
            }
        }

        if (!function_exists('formatCurrency')) {
            function formatCurrency($value)
            {
                return \App\Helpers\FormatService::formatCurrency($value);
            }
        }

        if (!function_exists('formatExtensionLabel')) {
            function formatExtensionLabel($extension)
            {
                $name = trim((string) ($extension['name'] ?? 'Extension'));
                $mode = strtolower(trim((string) ($extension['calculation_mode'] ?? 'fixed')));
                $value = abs((float) ($extension['calculation_value'] ?? 0));

                $nameContainsPercent = preg_match('/-?\d+(?:\.\d+)?\s*%$/', $name) === 1;
                $isPercentage = $mode === 'percentage' || $nameContainsPercent;

                if ($isPercentage) {
                    if ($value <= 0 && $nameContainsPercent) {
                        preg_match('/(-?\d+(?:\.\d+)?)\s*%$/', $name, $matches);
                        $value = abs((float) ($matches[1] ?? 0));
                    }

                    $displayValue = $value > 0 && $value < 1 ? $value * 100 : $value;
                    $formattedValue =
                        floor($displayValue) == $displayValue
                            ? number_format($displayValue, 0)
                            : number_format($displayValue, 2);
                    $baseName = trim((string) preg_replace('/\s*-?\d+(?:\.\d+)?\s*%$/', '', $name));
                    $normalizedValue = str_contains($formattedValue, '.')
                        ? rtrim(rtrim($formattedValue, '0'), '.')
                        : $formattedValue;

                    return ($baseName !== '' ? $baseName : $name) . ' ' . $normalizedValue . '%';
                }

                return $name;
            }
        }
    @endphp

    {{-- ── ORDER INFO ── --}}
    <div class="order-info-section">
        <table class="order-info">
            <tr>
                {{-- Left column --}}
                <td width="60%">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td class="lbl">Name</td>
                            <td class="sep">:</td>
                            <td>{{ $data['customer_name'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl" style="vertical-align:top;">Address</td>
                            <td class="sep" style="vertical-align:top;">:</td>
                            <td style="white-space: pre-line;">{{ $data['customer_address'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Description</td>
                            <td class="sep">:</td>
                            <td>{{ $data['description'] ?? '-' }}</td>
                        </tr>
                    </table>
                </td>
                {{-- Right column --}}
                <td width="40%">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td class="lbl" style="width:115px;">Quotation No.</td>
                            <td class="sep">:</td>
                            <td>{{ $data['quotation_number'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Payment Plan</td>
                            <td class="sep">:</td>
                            <td>{{ $data['payment_plan_label'] ?? '-' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── ITEMS ── --}}
    <div class="content-wrapper">
        <div class="items-table-wrap">
            <table class="items-table">
                <tbody>
                    @foreach ($items as $item)
                        @php
                            $isRoot = empty($item['parent_id']) && empty($item['parent_key']);

                            if ($isRoot) {
                                $rootCounter++;
                                $label = "{$rootCounter}.";
                                $pid = $item['id'] ?? ($item['parent_key'] ?? $rootCounter);
                                $childCounters[$pid] = 0;
                                $indentClass = '';
                                $parentKey = $item['id'] ?? $rootCounter;
                            } else {
                                $parentKey = $item['parent_id'] ?? $item['parent_key'];
                                $idx = $childCounters[$parentKey] ?? 0;
                                $label = alphabetIndex($idx) . '.';
                                $childCounters[$parentKey] = $idx + 1;
                                $indentClass = 'sub-item';
                            }

                            $amount = null;
                            if (empty($item['is_header'])) {
                                $amount = (float) ($item['quantity'] ?? 0) * (float) ($item['rate'] ?? 0);
                                $subtotal += $amount;
                            }

                            $description = $item['description'];
                        @endphp

                        @if (!empty($item['is_header']))
                            <tr class="header-row">
                                <td colspan="2">{{ $label }} {{ $description }}</td>
                            </tr>
                        @else
                            <tr class="item-row {{ $isRoot ? 'root' : '' }} {{ $indentClass }}">
                                <td class="col-desc">{{ $label }} {{ $description }}</td>
                                <td class="col-price">{{ formatCurrency($amount) }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Totals --}}
        @php
            $itemTaxExtensions = collect($items)
                ->filter(fn($item) => empty($item['is_header']))
                ->flatMap(function ($item) {
                    $lineAmount = (float) ($item['quantity'] ?? 0) * (float) ($item['rate'] ?? 0);
                    $memberName = $item['member_name'] ?? null;

                    return collect($item['taxes'] ?? [])
                        ->filter(function ($tax) {
                            $mode = (string) ($tax['calculation_mode'] ?? '');
                            $value = (float) ($tax['calculation_value'] ?? 0);

                            return in_array($mode, ['fixed', 'percentage'], true) && $value !== 0.0;
                        })
                        ->map(function ($tax) use ($lineAmount, $memberName) {
                            $mode = (string) ($tax['calculation_mode'] ?? '');
                            $value = (float) ($tax['calculation_value'] ?? 0);
                            $taxName = $tax['name'] ?? 'Tax';
                            if ($memberName) {
                                $taxName = "{$taxName} ({$memberName})";
                            }

                            return [
                                'key' => implode('|', [
                                    (int) ($tax['quotation_extension_master_id'] ?? 0),
                                    strtolower(trim((string) $taxName)),
                                    $mode,
                                    (string) $value,
                                    $memberName,
                                ]),
                                'name' => $taxName,
                                'calculation_mode' => $mode,
                                'calculation_value' => $value,
                                'amount' => $mode === 'percentage' ? ($lineAmount * $value) / 100 : $value,
                            ];
                        });
                })
                ->groupBy('key')
                ->map(function ($group) {
                    $first = $group->first();

                    return [
                        'name' => $first['name'] ?? 'Tax',
                        'calculation_mode' => $first['calculation_mode'] ?? 'fixed',
                        'calculation_value' => $first['calculation_value'] ?? 0,
                        'amount' => $group->sum('amount'),
                    ];
                })
                ->values();

            $quotationSubtotalExtensions = collect($data['extensions'] ?? []);

            if ($quotationSubtotalExtensions->isEmpty()) {
                $quotationSubtotalExtensions = collect($data['invoice_extensions'] ?? []);
            }

            $allExtensions = $itemTaxExtensions->concat($quotationSubtotalExtensions)->values();
        @endphp
        <div class="totals-wrapper">
            <table class="totals-table">
                <tbody>
                    <tr>
                        <td class="total-label">Sub Total:</td>
                        <td class="total-amount">{{ formatCurrency($subtotal) }}</td>
                    </tr>
                    @if ($allExtensions->isNotEmpty())
                        @foreach ($allExtensions as $extension)
                            <tr>
                                <td class="total-label">{{ formatExtensionLabel($extension) }}:</td>
                                <td class="total-amount">{{ formatCurrency($extension['amount'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    @endif
                    <tr class="total-row-grand">
                        <td class="total-label payment-info-head">Total Amount:</td>
                        <td class="total-amount payment-info-head">{{ formatCurrency($data['total_amount'] ?? $subtotal) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>

    {{-- ── FOOTER ── --}}
    <div class="footer-section">
        {{-- Compute active notes so variable is in scope for both the partial and footer condition --}}
        @php
            $activeNotes = collect($data['notes'] ?? [])
                ->filter(fn($n) => !empty(trim(strip_tags($n['description'] ?? ''))))
                ->sortBy('sort_order')
                ->values();
        @endphp

        {{-- Notes: rendered above footer if any description is filled; Tiptap HTML preserves alignment/formatting --}}
        @include('partials.report-notes')

        {{-- Module footer text from Report Template Settings --}}
        {{-- When no notes: always show footer_text (centered) or fallback message (centered) --}}
        @if (!empty($branding['footer_text']))
            <div class="footer-note" style="text-align:{{ $activeNotes->isEmpty() ? 'center' : 'right' }}">
                {!! nl2br(e($branding['footer_text'])) !!}</div>
        @endif

        @include('partials.report-signature-stamp')
    </div>


@endsection
