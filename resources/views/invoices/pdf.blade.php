<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Invoice - {{ $data['invoice_number'] ?? 'INV-0001' }}</title>
    <style>
        @page {
            size: A4;
            margin: 1.5cm 1.8cm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.45;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
        }

        /* ── Header ── */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .logo-cell {
            width: 42%;
            vertical-align: middle;
        }

        .info-cell {
            width: 58%;
            text-align: right;
            vertical-align: middle;
        }

        /* Fix: explicit height in px, no object-fit (not supported in PDF renderers) */
        .logo-cell img {
            display: block;
            width: auto;
            height: 52px;
            max-width: 180px;
            margin: 0;
        }

        .company-name {
            font-size: 12px;
            font-weight: bold;
            color: #222;
            margin-bottom: 2px;
            display: block;
        }

        .company-details {
            font-size: 9px;
            color: #444;
            line-height: 1.5;
        }

        .company-reg {
            font-size: 9px;
            font-weight: bold;
            margin-top: 3px;
        }

        /* ── Title Bar ── */
        .title-bar {
            background-color: {{ $branding['title_color'] ?? '#40A09D' }};
            color: #fff;
            text-align: center;
            font-weight: bold;
            font-size: 13px;
            padding: 5px 0;
            letter-spacing: 4px;
            margin-bottom: 12px;
        }

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
            width: 105px;
        }

        .sep {
            width: 12px;
        }

        .lbl-r {
            font-weight: bold;
            white-space: nowrap;
            width: 90px;
        }

        /* ── Due Date highlight ── */
        .due-date-urgent {
            color: #c0392b;
            font-weight: bold;
        }

        /* ── Helper Name ── */
        .helper-name {
            font-weight: bold;
            font-size: 10px;
            border-top: 1px solid #d0d0d0;
            padding-top: 6px;
            margin-bottom: 8px;
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

        /* Sub-item indent */
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

        /* ── Footer ── */
        .footer-section {
            font-size: 9px;
            padding-top: 8px;
            border-top: 1px solid #d0d0d0;
        }

        .footer-note {
            text-align: center;
            margin-bottom: 6px;
            line-height: 1.5;
            color: #333;
        }

        .stamp-sig-row {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .stamp-sig-row td {
            vertical-align: bottom;
        }

        .updated-date {
            text-align: right;
            font-weight: bold;
            font-size: 9px;
            margin-top: 12px;
            color: #333;
        }
    </style>
</head>

<body>

    {{-- ── HEADER ── --}}
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @if (($is_pdf ?? false) && !empty($branding['logo_path_absolute']) && file_exists($branding['logo_path_absolute']))
                    <img src="{{ $branding['logo_path_absolute'] }}" alt="Company Logo">
                @elseif(!empty($branding['logo_url']))
                    <img src="{{ $branding['logo_url'] }}" alt="Company Logo">
                @else
                    @if ($is_pdf ?? false)
                        <img src="{{ public_path('logo-primary.png') }}" alt="Company Logo">
                    @else
                        <img src="/logo-primary.png" alt="Company Logo">
                    @endif
                @endif
            </td>
            <td class="info-cell">
                <span class="company-name">{{ $branding['company_name'] ?? 'Urban Care Employment Agency' }}</span>
                <div class="company-details">
                    {!! nl2br(e($branding['company_address'] ?? "931 Yishun Central 1\n#01-109, Singapore 760931")) !!}
                    @if (!empty($branding['company_phone']))
                        <br>Tel: {{ $branding['company_phone'] }}
                    @endif
                    @if (!empty($branding['company_email']))
                        <br>Email: {{ $branding['company_email'] }}
                    @endif
                </div>
                <div class="company-reg">
                    @if ($data['sales_registration_number'] ?? false)
                        REGISTRATION NO. {{ $data['sales_registration_number'] }}&nbsp;&nbsp;
                    @endif
                    LICENCE NO. 25C2708
                </div>
            </td>
        </tr>
    </table>

    {{-- ── TITLE BAR ── --}}
    <div class="title-bar">INVOICE</div>

    <div class="content-wrapper">

        {{-- ── ORDER INFO ── --}}
        <table class="order-info-grid">
            <tr>
                {{-- Left: Customer details --}}
                <td width="58%">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td class="lbl">Customer No.</td>
                            <td class="sep">:</td>
                            <td>{{ $data['customer_number'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Customer</td>
                            <td class="sep">:</td>
                            <td>{{ $data['customer_name'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl" style="vertical-align:top;">Address</td>
                            <td class="sep" style="vertical-align:top;">:</td>
                            <td>{!! $data['customer_address'] ?? '-' !!}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Contact</td>
                            <td class="sep">:</td>
                            <td>{{ $data['customer_contact'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Email</td>
                            <td class="sep">:</td>
                            <td>{{ $data['customer_email'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Description</td>
                            <td class="sep">:</td>
                            <td>{{ $data['description'] ?? '-' }}</td>
                        </tr>
                    </table>
                </td>
                {{-- Right: Invoice meta --}}
                <td width="42%">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td class="lbl-r">Order No.</td>
                            <td class="sep">:</td>
                            <td>{{ $data['order_number'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl-r">Invoice No.</td>
                            <td class="sep">:</td>
                            <td>{{ $data['invoice_number'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl-r">Invoice Date</td>
                            <td class="sep">:</td>
                            <td>{{ $data['invoice_date'] ?? date('d/m/Y') }}</td>
                        </tr>
                        <tr>
                            <td class="lbl-r">Due Date</td>
                            <td class="sep">:</td>
                            <td class="{{ !empty($data['due_date']) ? 'due-date-urgent' : '' }}">
                                {{ $data['due_date'] ?? '-' }}
                            </td>
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

            function hasChildren($item, $allItems)
            {
                return collect($allItems)
                    ->filter(function ($child) use ($item) {
                        $parentIdMatch = !empty($child['parent_id']) && $child['parent_id'] == $item['id'];
                        $parentKeyMatch =
                            !empty($child['parent_key']) &&
                            !empty($item['_key']) &&
                            $child['parent_key'] == $item['_key'];
                        return $parentIdMatch || $parentKeyMatch;
                    })
                    ->isNotEmpty();
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

                $descriptionText = e($item['description']);

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

        {{-- ── HELPER NAME ── --}}
        <div class="helper-name">Name of Helper Deployed : {{ strtoupper($data['maid_name'] ?? '-') }}</div>

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

        {{-- ── FOOTER ── --}}
        <div class="footer-section">
            @if (!empty($branding['footer_text']))
                <div class="footer-note">{!! nl2br(e($branding['footer_text'])) !!}</div>
            @else
                <div class="footer-note">
                    Paynow to UEN 53496387X or Bank Transfer to DBS Business Multi Currency Account 072-131956-0.<br>
                    For further assistance, please contact us at 8785 5651.
                </div>
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

            <div class="updated-date">UPDATED: {{ date('d/m/Y') }}</div>
        </div>

    </div>
</body>

</html>
