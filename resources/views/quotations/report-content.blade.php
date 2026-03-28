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
    <style>
        /* ── Divider ── */
        .section-divider {
            border: none;
            border-top: 1px solid #d0d0d0;
            margin: 10px 0;
        }

        /* ── Order Info ── */
        .order-info-section {
            padding: 0;
            margin-bottom: 12px;
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
            margin-bottom: 8px;
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
            text-align: right;
            padding: 6px 0 4px;
            margin-top: 4px;
        }

        .total-label {
            font-size: 11px;
            color: #555;
        }

        .total-amount {
            font-weight: bold;
            font-size: 12px;
        }

        /* ── Footer ── */
        .footer-section {
            padding: 12px 0 0;
            font-size: 10px;
            border-top: none;
        }

        .footer-note {
            text-align: center;
            margin-bottom: 8px;
            line-height: 1.5;
            color: #333;
        }

        .updated-date {
            text-align: right;
            font-weight: bold;
            font-size: 10px;
            margin-top: 16px;
            color: #333;
        }

        .stamp-sig-row {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
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

        function alphabetIndex($i)
        {
            $alphabet = 'abcdefghijklmnopqrstuvwxyz';
            return $i < 26 ? $alphabet[$i] : $alphabet[intdiv($i, 26) - 1] . $alphabet[$i % 26];
        }

        function formatCurrency($value)
        {
            return \App\Helpers\FormatService::formatCurrency($value);
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
                            <td style="white-space: pre-line;">{!! nl2br(e($data['customer_address'] ?? '-')) !!}</td>
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
                            <td class="lbl">Placement Fee</td>
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

                    return collect($item['taxes'] ?? [])
                        ->filter(function ($tax) {
                            $mode = (string) ($tax['calculation_mode'] ?? '');
                            $value = (float) ($tax['calculation_value'] ?? 0);

                            return in_array($mode, ['fixed', 'percentage'], true) && $value > 0;
                        })
                        ->map(function ($tax) use ($lineAmount) {
                            $mode = (string) ($tax['calculation_mode'] ?? '');
                            $value = (float) ($tax['calculation_value'] ?? 0);

                            return [
                                'key' => implode('|', [
                                    (int) ($tax['quotation_extension_master_id'] ?? 0),
                                    strtolower(trim((string) ($tax['name'] ?? 'Tax'))),
                                    $mode,
                                    (string) $value,
                                ]),
                                'name' => $tax['name'] ?? 'Tax',
                                'amount' => $mode === 'percentage' ? ($lineAmount * $value) / 100 : $value,
                            ];
                        });
                })
                ->groupBy('key')
                ->map(function ($group) {
                    $first = $group->first();

                    return [
                        'name' => $first['name'] ?? 'Tax',
                        'amount' => $group->sum('amount'),
                    ];
                })
                ->values();
        @endphp
        <div class="totals-wrapper">
            <div>
                <span class="total-label">Sub Total:&nbsp;</span>
                <span class="total-amount">{{ formatCurrency($subtotal) }}</span>
            </div>
            @if ($itemTaxExtensions->isNotEmpty())
                @foreach ($itemTaxExtensions as $itemTax)
                    <div>
                        <span class="total-label">{{ $itemTax['name'] ?? 'Tax' }} (Item Tax):&nbsp;</span>
                        <span class="total-amount">{{ formatCurrency($itemTax['amount'] ?? 0) }}</span>
                    </div>
                @endforeach
            @endif
            @if (!empty($data['extensions']) && count($data['extensions']) > 0)
                @foreach ($data['extensions'] as $extension)
                    <div>
                        <span class="total-label">{{ $extension['name'] ?? 'Quotation Extension' }}:&nbsp;</span>
                        <span class="total-amount">{{ formatCurrency($extension['amount'] ?? 0) }}</span>
                    </div>
                @endforeach
            @endif
            <div>
                <span class="total-label">Total Amount:&nbsp;</span>
                <span class="total-amount">{{ formatCurrency($data['total_amount'] ?? $subtotal) }}</span>
            </div>
        </div>
    </div>

    {{-- ── FOOTER ── --}}
    <div class="footer-section">
        @if (!empty($branding['footer_text']))
            <div class="footer-note">{!! nl2br(e($branding['footer_text'])) !!}</div>
        @else
            @forelse($data['notes'] ?? [] as $note)
                <div class="footer-note">{!! $note['description'] ?? '' !!}</div>
            @empty
                <div class="footer-note">Thank you for your business!</div>
            @endforelse
        @endif

        @include('partials.report-signature-stamp')

        <div class="updated-date">UPDATED: {{ date('d/m/Y') }}</div>
    </div>

@endsection
