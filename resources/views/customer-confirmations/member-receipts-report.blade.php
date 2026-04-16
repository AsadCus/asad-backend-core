@extends('layout-report')

@section('document-title', 'Receipt Statement - ' . ($data['customer_name'] ?? 'Customer'))

@section('extra-company-reg')
    @if (!empty($data['customer_number']))
        CUSTOMER NO. {{ $data['customer_number'] }}&nbsp;&nbsp;
    @endif
@endsection

@section('title-bar')
    RECEIPT STATEMENT
@endsection

@push('styles')
    <style>
        /* ── Customer Info Section ── */
        .customer-info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .customer-info-grid td {
            vertical-align: top;
            padding: 1px 0;
            font-size: 11px;
        }

        .lbl {
            font-weight: bold;
            white-space: nowrap;
            width: 110px;
        }

        .sep {
            width: 12px;
        }

        .lbl-r {
            font-weight: bold;
            white-space: nowrap;
            width: 120px;
        }

        /* ── Receipt Block ── */
        .receipt-block {
            margin-bottom: 14px;
            page-break-inside: avoid;
        }

        .receipt-block-header {
            background-color: #f0f0f0;
            border-left: 3px solid {{ $branding['title_color'] ?? '#c05427' }};
            padding: 4px 8px;
            margin-bottom: 6px;
            font-size: 11px;
        }

        .receipt-block-header table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .receipt-block-header td {
            font-size: 11px;
            padding: 1px 4px 1px 0;
            width: 25%;
            vertical-align: top;
        }

        .receipt-block-header .lbl-rh {
            font-weight: bold;
            white-space: nowrap;
            display: inline-block;
            min-width: 78px;
        }

        /* ── Items Section ── */
        .items-section {
            border-top: 1.5px solid #333;
            border-bottom: 1.5px solid #333;
            margin-bottom: 6px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table thead th {
            border-bottom: 1px solid #333;
            padding: 3px 0;
            font-weight: bold;
            font-size: 11px;
            text-align: left;
        }

        .items-table thead th.col-rate,
        .items-table thead th.col-total {
            text-align: right;
        }

        .items-table td {
            padding: 3px 0;
            vertical-align: top;
            font-size: 11px;
        }

        .col-desc { width: auto; }
        .col-rate { width: 80px; text-align: right; white-space: nowrap; }
        .col-total { width: 90px; text-align: right; white-space: nowrap; }

        .sub-item .col-desc { padding-left: 20px; }

        .header-row td {
            font-weight: bold;
            padding: 4px 0 2px;
            border-bottom: 1px solid #ccc;
            font-size: 11px;
        }

        .item-row.root .col-desc { font-weight: bold; }

        /* ── Totals ── */
        .totals-wrapper {
            width: 100%;
            text-align: right;
            padding: 4px 0 2px;
            margin-top: 4px;
        }

        .totals-table {
            width: 380px;
            display: inline-table;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .totals-table td { padding: 1.5px 0; }

        .total-label {
            width: 50%;
            text-align: right;
            padding-right: 12px;
            font-size: 11px;
            color: #555;
        }

        .total-amount {
            width: 50%;
            text-align: right;
            font-weight: normal;
            font-size: 12px;
            white-space: nowrap;
        }

        .totals-table .total-row-grand .total-label { font-weight: bold; }
        .totals-table .total-row-grand td {
            border-top: 1px solid #cfcfcf;
            padding-top: 4px;
        }

        /* ── Divider between receipts ── */
        .receipt-divider {
            border: none;
            border-top: 1px dashed #bbb;
            margin: 12px 0;
        }

        /* ── No receipts message ── */
        .no-receipts {
            padding: 16px;
            text-align: center;
            font-size: 12px;
            color: #888;
        }

        /* ── Status badge ── */
        .status-badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .footer-section {
            padding: 12px 0 0;
            font-size: 10px;
            border-top: none;
        }

        .footer-note {
            text-align: right;
            margin-bottom: 8px;
            line-height: 1.5;
            color: #333;
        }
    </style>
@endpush

@section('report-content')

    @php
        function ccFormatCurrency($value)
        {
            return \App\Helpers\FormatService::formatCurrency($value);
        }

        function ccToAlphabet($num)
        {
            $alphabet = 'abcdefghijklmnopqrstuvwxyz';
            if ($num - 1 < 26) return $alphabet[$num - 1];
            $firstLetter = $alphabet[floor(($num - 1) / 26) - 1];
            $secondLetter = $alphabet[($num - 1) % 26];
            return $firstLetter . $secondLetter;
        }

        function ccRenderItem($item, $allItems, $level = 0, &$counter, &$subtotal, $subCounter = 0)
        {
            $itemNumber = '';
            $isRoot = false;

            if ($level === 0) {
                $counter++;
                $itemNumber = $counter . '.';
                $isRoot = true;
            } elseif ($level === 1) {
                $itemNumber = ccToAlphabet($subCounter) . '.';
            }

            $indentClass = $level > 0 ? 'sub-item' : '';
            $rootClass = $isRoot ? 'root' : '';

            $rate = floatval($item['rate'] ?? 0);
            $qty = floatval($item['quantity'] ?? 1);
            $itemTotal = $rate * $qty;

            if (!($item['is_header'] ?? false)) {
                $subtotal += $itemTotal;
            }

            $descriptionText = e($item['description'] ?? '');
            $children = collect($allItems)
                ->filter(function ($child) use ($item) {
                    $parentIdMatch = !empty($child['parent_id']) && $child['parent_id'] == $item['id'];
                    $parentKeyMatch = !empty($child['parent_key']) && !empty($item['_key']) && $child['parent_key'] == $item['_key'];
                    return $parentIdMatch || $parentKeyMatch;
                })
                ->sortBy('sort_order');

            $rateDisplay = !($item['is_header'] ?? false) && $rate ? ccFormatCurrency($rate) : '';
            $totalDisplay = !($item['is_header'] ?? false) && $itemTotal ? ccFormatCurrency($itemTotal) : '';

            if (!empty($item['is_header'])) {
                echo '<tr class="header-row"><td colspan="3">' . $itemNumber . ' ' . $descriptionText . '</td></tr>';
            } else {
                echo '<tr class="item-row ' . $rootClass . ' ' . $indentClass . '">';
                echo '<td class="col-desc">' . $itemNumber . ' ' . $descriptionText . '</td>';
                echo '<td class="col-rate">' . $rateDisplay . '</td>';
                echo '<td class="col-total">' . $totalDisplay . '</td>';
                echo '</tr>';
            }

            $childSubCounter = 0;
            foreach ($children as $child) {
                $childSubCounter++;
                ccRenderItem($child, $allItems, $level + 1, $counter, $subtotal, $childSubCounter);
            }
        }

        $paymentStatusLabels = [
            'pending_payment' => 'Pending Payment',
            'partially_paid'  => 'Partially Paid',
            'fully_paid'      => 'Fully Paid',
            'overpaid'        => 'Overpaid',
            'cancelled'       => 'Cancelled',
        ];
    @endphp

    <div class="content-wrapper">

        {{-- ── CUSTOMER INFO ── --}}
        <table class="customer-info-grid">
            <tr>
                {{-- Left: Customer details --}}
                <td width="55%">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td class="lbl">Name</td>
                            <td class="sep">:</td>
                            <td>{{ $data['customer_name'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Customer No.</td>
                            <td class="sep">:</td>
                            <td>{{ $data['customer_number'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">NRIC / IC No.</td>
                            <td class="sep">:</td>
                            <td>{{ $data['nric_number'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl" style="vertical-align:top;">Address</td>
                            <td class="sep" style="vertical-align:top;">:</td>
                            <td style="white-space:pre-line;">{{ $data['customer_address'] ?? '-' }}</td>
                        </tr>
                    </table>
                </td>
                {{-- Right: Booking details --}}
                <td width="45%">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td class="lbl-r">Email</td>
                            <td class="sep">:</td>
                            <td>{{ $data['customer_email'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl-r">Contact</td>
                            <td class="sep">:</td>
                            <td>{{ $data['customer_contact'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl-r">Package</td>
                            <td class="sep">:</td>
                            <td>{{ $data['package_name'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl-r">Applied Date</td>
                            <td class="sep">:</td>
                            <td>{{ $data['date_of_application'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl-r">Payment Status</td>
                            <td class="sep">:</td>
                            <td>
                                <span class="status-badge">
                                    {{ $paymentStatusLabels[$data['payment_status'] ?? ''] ?? ucfirst(str_replace('_', ' ', $data['payment_status'] ?? '-')) }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="lbl-r">Total Paid</td>
                            <td class="sep">:</td>
                            <td style="font-weight: bold;">{{ ccFormatCurrency($data['paid_amount'] ?? 0) }} / {{ ccFormatCurrency($data['total_amount'] ?? 0) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- ── RECEIPTS ── --}}
        @if (empty($data['receipts']))
            <div class="no-receipts">No receipts found for this member.</div>
        @else
            @foreach ($data['receipts'] as $receiptIndex => $receipt)
                @if ($receiptIndex > 0)
                    <hr class="receipt-divider">
                @endif

                @php
                    $items = $receipt['items'] ?? [];
                    $rCounter = 0;
                    $rSubtotal = 0;
                    $rootItems = collect($items)
                        ->filter(fn($item) => empty($item['parent_id']) && empty($item['parent_key']))
                        ->sortBy('sort_order');
                @endphp

                <div class="receipt-block">
                    {{-- Receipt mini-header --}}
                    <div class="receipt-block-header">
                        <table>
                            <tr>
                                <td><span class="lbl-rh">Receipt No.:</span> {{ $receipt['receipt_number'] ?? '-' }}</td>
                                <td><span class="lbl-rh">Date:</span> {{ $receipt['receipt_date'] ?? '-' }}</td>
                                <td><span class="lbl-rh">Invoice No.:</span> {{ $receipt['invoice_number'] ?? '-' }}</td>
                                <td><span class="lbl-rh">Method:</span> {{ $receipt['payment_method_label'] ?? '-' }}</td>
                            </tr>
                            @if (!empty($receipt['order_number']) && $receipt['order_number'] !== '-')
                                <tr>
                                    <td colspan="2"><span class="lbl-rh">Order No.:</span> {{ $receipt['order_number'] }}</td>
                                    @if (!empty($receipt['reference']))
                                        <td colspan="2"><span class="lbl-rh">Reference:</span> {{ $receipt['reference'] }}</td>
                                    @else
                                        <td colspan="2"></td>
                                    @endif
                                </tr>
                            @endif
                        </table>
                    </div>

                    {{-- Items table --}}
                    @if (!empty($items))
                        <div class="items-section">
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th class="col-desc">Item Description</th>
                                        <th class="col-rate">Cost</th>
                                        <th class="col-total">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rootItems as $item)
                                        @php ccRenderItem($item, $items, 0, $rCounter, $rSubtotal, 0); @endphp
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    {{-- Totals --}}
                    <div class="totals-wrapper">
                        <table class="totals-table">
                            <tbody>
                                @if (!empty($items))
                                    <tr>
                                        <td class="total-label">Sub Total:</td>
                                        <td class="total-amount">{{ ccFormatCurrency($receipt['subtotal_amount'] ?? $rSubtotal) }}</td>
                                    </tr>
                                @endif
                                @if (!empty($receipt['extensions']) && count($receipt['extensions']) > 0)
                                    @foreach ($receipt['extensions'] as $extension)
                                        <tr>
                                            <td class="total-label">{{ $extension['name'] ?? 'Extension' }}:</td>
                                            <td class="total-amount">{{ ccFormatCurrency($extension['amount'] ?? 0) }}</td>
                                        </tr>
                                    @endforeach
                                @endif
                                <tr class="total-row-grand">
                                    <td class="total-label">Total Amount:</td>
                                    <td class="total-amount">{{ ccFormatCurrency($receipt['total_amount'] ?? 0) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- Notes per receipt --}}
                    @if (!empty($receipt['notes']))
                        @php
                            $receiptActiveNotes = collect($receipt['notes'])
                                ->filter(fn($n) => !empty(trim(strip_tags($n['description'] ?? ''))))
                                ->sortBy('sort_order')
                                ->values();
                        @endphp
                        @if ($receiptActiveNotes->isNotEmpty())
                            <div class="report-notes" style="border-top: 1px solid #d0d0d0; padding-top: 6px; margin-top: 8px; margin-bottom: 0;">
                                <p class="report-notes-heading">Notes</p>
                                @foreach ($receiptActiveNotes as $note)
                                    <div class="note-item">{!! $note['description'] !!}</div>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </div>
            @endforeach
        @endif

    </div>

    {{-- ── FOOTER ── --}}
    <div class="footer-section">
        @php
            $activeNotes = collect([]);
        @endphp

        @if (!empty($branding['footer_text']))
            <div class="footer-note" style="text-align:center">{!! nl2br(e($branding['footer_text'])) !!}</div>
        @else
            <div class="footer-note" style="text-align:center">Thank you for your business!</div>
        @endif

        @include('partials.report-signature-stamp')
    </div>

@endsection
