@extends('layout-report')

@section('document-title', 'Official Receipt - ' . ($data['receipt_number'] ?? 'OR-2025-0001'))

@section('extra-company-reg')
    @if ($data['sales_registration_number'] ?? false)
        REGISTRATION NO. {{ $data['sales_registration_number'] }}&nbsp;&nbsp;
    @endif
@endsection

@section('title-bar')
    OFFICIAL RECEIPT
@endsection


@push('styles')
    <style>
        /* ── Content Wrapper ── */
        .content-wrapper {
            padding: 0 30px;
        }

        /* ── Order Info ── */
        .order-info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .order-info-grid td {
            vertical-align: top;
            padding: 1px 0;
            font-size: 10px;
        }

        .lbl {
            font-weight: bold;
            white-space: nowrap;
            width: 90px;
        }

        .sep {
            width: 12px;
        }

        .lbl-r {
            font-weight: bold;
            white-space: nowrap;
            width: 100px;
        }

        /* ── Items Table ── */
        .items-section {
            border-top: 1.5px solid #333;
            border-bottom: 1.5px solid #333;
            padding: 6px 0;
            margin-bottom: 12px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table thead th {
            border-bottom: 1px solid #333;
            padding: 3px 0;
            font-weight: bold;
            font-size: 10px;
            text-align: left;
        }

        .items-table thead th.col-rate,
        .items-table thead th.col-total {
            text-align: right;
        }

        .items-table td {
            padding: 2.5px 0;
            vertical-align: top;
            font-size: 10px;
        }

        .col-desc {
            width: auto;
        }

        .col-rate {
            width: 80px;
            text-align: right;
            white-space: nowrap;
        }

        .col-total {
            width: 90px;
            text-align: right;
            white-space: nowrap;
        }

        .sub-item .col-desc {
            padding-left: 20px;
        }

        .item-row {
            border-bottom: 1px solid #ebebeb;
        }

        .parent-item-end td {
            height: 5px;
            line-height: 5px;
            border: none;
        }

        /* ── Totals ── */
        .totals-wrapper {
            text-align: right;
            padding: 5px 0 2px;
            border-top: 1px solid #ccc;
            margin-top: 4px;
        }

        .total-label {
            font-size: 10px;
            color: #555;
        }

        .total-amount {
            font-weight: bold;
            font-size: 11px;
        }

        /* ── Remarks ── */
        .remarks-section {
            margin-bottom: 14px;
        }

        .remarks-label {
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 4px;
        }

        .remarks-box {
            border: 1px solid #999;
            min-height: 52px;
            padding: 6px 8px;
            font-size: 10px;
            color: #333;
        }
    </style>
@endpush

@section('report-content')

    <div class="content-wrapper">

        {{-- ── ORDER INFO ── --}}
        <table class="order-info-grid">
            <tr>
                {{-- Left --}}
                <td width="55%">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td class="lbl">Name</td>
                            <td class="sep">:</td>
                            <td>{{ $data['customer_name'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl" style="vertical-align:top;">Address</td>
                            <td class="sep" style="vertical-align:top;">:</td>
                            <td>{!! $data['customer_address'] ?? '-' !!}</td>
                        </tr>
                    </table>
                </td>
                {{-- Right --}}
                <td width="45%">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td class="lbl-r">Receipt No.</td>
                            <td class="sep">:</td>
                            <td>{{ $data['receipt_number'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl-r">Receipt Date</td>
                            <td class="sep">:</td>
                            <td>{{ $data['receipt_date'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl-r">Order No.</td>
                            <td class="sep">:</td>
                            <td>{{ $data['order_number'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl-r">Payment Method</td>
                            <td class="sep">:</td>
                            <td>{{ $data['payment_method_label'] ?? '-' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        @php
            function formatCurrency($value)
            {
                return \App\Helpers\FormatService::formatCurrency($value);
            }

            function toAlphabet($num)
            {
                $alphabet = 'abcdefghijklmnopqrstuvwxyz';
                if ($num - 1 < 26) {
                    return $alphabet[$num - 1];
                }
                $firstLetter = $alphabet[floor(($num - 1) / 26) - 1];
                $secondLetter = $alphabet[($num - 1) % 26];
                return $firstLetter . $secondLetter;
            }

            function renderItem($item, $allItems, $level = 0, &$counter, &$subtotal, $subCounter = 0)
            {
                $itemNumber = '';
                if ($level === 0) {
                    $counter++;
                    $itemNumber = $counter . '.';
                } elseif ($level === 1) {
                    $itemNumber = toAlphabet($subCounter) . '.';
                }

                $indentClass = $level > 0 ? 'sub-item' : '';
                $rate = floatval($item['rate'] ?? 0);
                $qty = floatval($item['quantity'] ?? 1);
                $itemTotal = $rate * $qty;

                if (!$item['is_header']) {
                    $subtotal += $itemTotal;
                }

                $descriptionText = e($item['description'] ?? '');
                $children = collect($allItems)
                    ->filter(function ($child) use ($item) {
                        $parentIdMatch = !empty($child['parent_id']) && $child['parent_id'] == $item['id'];
                        $parentKeyMatch =
                            !empty($child['parent_key']) &&
                            !empty($item['_key']) &&
                            $child['parent_key'] == $item['_key'];
                        return $parentIdMatch || $parentKeyMatch;
                    })
                    ->sortBy('sort_order');

                $rateDisplay = !$item['is_header'] && $rate ? formatCurrency($rate) : '';
                $totalDisplay = !$item['is_header'] && $itemTotal ? formatCurrency($itemTotal) : '';

                echo '<tr class="item-row ' . $indentClass . '">';
                echo '<td class="col-desc">' . $itemNumber . ' ' . $descriptionText . '</td>';
                echo '<td class="col-rate">' . $rateDisplay . '</td>';
                echo '<td class="col-total">' . $totalDisplay . '</td>';
                echo '</tr>';

                $childSubCounter = 0;
                foreach ($children as $child) {
                    $childSubCounter++;
                    renderItem($child, $allItems, $level + 1, $counter, $subtotal, $childSubCounter);
                }

                if ($level === 0) {
                    echo '<tr class="parent-item-end"><td colspan="3"></td></tr>';
                }
            }

            $counter = 0;
            $subtotal = 0;
            $rootItems = collect($items ?? [])
                ->filter(fn($item) => empty($item['parent_id']) && empty($item['parent_key']))
                ->sortBy('sort_order');
        @endphp

        {{-- ── ITEMS ── --}}
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
                        @php renderItem($item, $items, 0, $counter, $subtotal, 0); @endphp
                    @endforeach
                </tbody>
            </table>

            <div class="totals-wrapper">
                <span class="total-label">Total Amount:&nbsp;</span>
                <span class="total-amount">{{ formatCurrency($subtotal) }}</span>
            </div>
        </div>

        {{-- ── REMARKS ── --}}
        <div class="remarks-section">
            <div class="remarks-label">Remarks:</div>
            <div class="remarks-box">{{ $data['description'] ?? '' }}</div>
        </div>

        {{-- ── FOOTER ── --}}
        <div class="footer-section">
            @if (!empty($branding['footer_text']))
                <div class="footer-note">{!! nl2br(e($branding['footer_text'])) !!}</div>
            @else
                <div class="footer-note">Thank you for your business!</div>
            @endif

            @if (!empty($branding['show_stamp']) || !empty($branding['show_signature']))
                <table class="stamp-sig-row">
                    <tr>
                        <td>
                            @if (!empty($branding['show_stamp']))
                                @if (($is_pdf ?? false) && !empty($branding['stamp_path_absolute']) && file_exists($branding['stamp_path_absolute']))
                                    <img src="{{ $branding['stamp_path_absolute'] }}" alt="Company Stamp"
                                        style="height:70px; width:auto; display:block;">
                                @elseif(!empty($branding['stamp_url']))
                                    <img src="{{ $branding['stamp_url'] }}" alt="Company Stamp"
                                        style="height:70px; width:auto; display:block;">
                                @endif
                            @endif
                        </td>
                        <td style="text-align:right;">
                            @if (!empty($branding['show_signature']))
                                <p style="font-size:9px; margin:0 0 3px 0;">Authorised Signature</p>
                                @if (
                                    ($is_pdf ?? false) &&
                                        !empty($branding['signature_path_absolute']) &&
                                        file_exists($branding['signature_path_absolute']))
                                    <img src="{{ $branding['signature_path_absolute'] }}" alt="Authorised Signature"
                                        style="height:52px; width:auto; display:block; margin-left:auto;">
                                @elseif(!empty($branding['signature_url']))
                                    <img src="{{ $branding['signature_url'] }}" alt="Authorised Signature"
                                        style="height:52px; width:auto; display:block; margin-left:auto;">
                                @endif
                            @endif
                        </td>
                    </tr>
                </table>
            @endif
        </div>

    </div>

@endsection
