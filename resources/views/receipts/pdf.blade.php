<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Official Receipt - {{ $data['receipt_number'] ?? 'OR-2025-0001' }}</title>
    <style>
        @page {
            size: A4;
            margin: 1.2cm 1.5cm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #000;
        }

        /* Header & Logo */
        .header-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }

        .logo-cell {
            width: 45%;
            vertical-align: top;
        }

        .info-cell {
            width: 55%;
            text-align: right;
            vertical-align: top;
        }

        .logo-cell img {
            max-width: 320px;
            max-height: 160px;
            height: auto;
            width: auto;
            display: block;
        }

        /* Title Bar */
        .title-bar {
            background-color: #40A09D;
            color: white;
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            padding: 6px;
            letter-spacing: 3px;
            margin-bottom: 15px;
        }

        /* Content Wrapper */
        .content-wrapper {
            padding: 0 40px;
        }

        /* Order Info Grid */
        .order-info-section {
            margin-bottom: 16px;
        }

        .order-info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .order-info-grid td {
            vertical-align: top;
            padding: 0px;
        }

        .order-info-grid .label-cell {
            white-space: nowrap;
            font-weight: bold;
        }

        /* Helper Name */
        .helper-name {
            font-weight: bold;
            margin-bottom: 10px;
        }

        /* Items Section */
        .items-section {
            border-bottom: 1px solid gray;
            padding: 10px 0;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .items-table thead th {
            border-bottom: 1px solid #000;
            padding: 4px 0;
            font-weight: bold;
            text-align: left;
        }

        .items-table thead th.col-qty {
            width: 60px;
            text-align: right;
        }

        .items-table thead th.col-rate {
            width: 80px;
            text-align: right;
        }

        .items-table thead th.col-total {
            width: 100px;
            text-align: right;
        }

        .items-table td {
            padding: 2px 0;
            vertical-align: top;
        }

        .col-desc {
            width: auto;
        }

        .col-qty,
        .col-rate,
        .col-total {
            text-align: right;
            white-space: nowrap;
        }

        /* Sub-item indentation */
        .sub-item .col-desc {
            padding-left: 24px;
        }

        /* Item row border */
        .item-row {
            border-bottom: 1px solid #e5e7eb;
        }

        /* Spacing after parent items */
        .parent-item-end td {
            height: 8px;
            line-height: 8px;
            border: none;
        }

        /* Totals */
        .totals-wrapper {
            width: 100%;
            margin-top: 15px;
            text-align: right;
        }

        .total-amount {
            font-weight: bold;
            font-size: 12px;
        }

        /* Remarks Section */
        .remarks-section {
            margin-top: 20px;
            margin-bottom: 20px;
        }

        .remarks-label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .remarks-box {
            border: 1px solid #000;
            min-height: 60px;
            padding: 8px;
        }

        /* Footer */
        .footer-section {
            clear: both;
            padding: 15px 0 0;
            font-size: 10px;
        }

        .footer-note {
            text-align: center;
            margin-bottom: 10px;
            line-height: 1.4;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                <img src="{{ public_path('logo_agency.png') }}" alt="Urban Care Logo">
            </td>
            <td class="info-cell">
                <div>
                    <b style="margin-bottom: 4px; font-size: 13px; color: #333;">Urban Care Employment Agency</b><br>
                    931 Yishun Central 1<br>
                    #01-109, Singapore 760931<br>
                    <div style="margin-top: 4px;">
                        @if ($data['sales_registration_number'] ?? false)
                            <b>REGISTRATION NO. {{ $data['sales_registration_number'] }}</b><br>
                        @endif
                        <b>LICENCE NO. 25C2708</b>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Title Bar -->
    <div class="title-bar">OFFICIAL RECEIPT</div>

    <div class="content-wrapper">
        <!-- Order Information -->
        <div class="order-info-section">
            <table class="order-info-grid">
                <tr>
                    <td class="label-cell">Name</td>
                    <td>: {{ $data['customer_name'] ?? '-' }}</td>
                    <td class="label-cell">Receipt Number</td>
                    <td>: {{ $data['receipt_number'] ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label-cell" style="vertical-align: top;">Address</td>
                    <td style="vertical-align: top;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="width: fit-content; vertical-align: top; padding: 0;">:</td>
                                <td style="vertical-align: top; padding: 0;">{!! $data['customer_address'] ?? '-' !!}</td>
                            </tr>
                        </table>
                    </td>
                    <td class="label-cell">Receipt Date</td>
                    <td>: {{ $data['receipt_date'] ?? '-' }}</td>
                </tr>
                <tr>
                    <td></td>
                    <td></td>
                    <td class="label-cell">Order Number</td>
                    <td>: {{ $data['order_number'] ?? '-' }}</td>
                </tr>
                <tr>
                    <td></td>
                    <td></td>
                    <td class="label-cell">Payment Method</td>
                    <td>: {{ $data['payment_method_label'] ?? '-' }}</td>
                </tr>
            </table>
        </div>

        @php
            // Helper functions
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
                // Generate item number
                $itemNumber = '';
                if ($level === 0) {
                    $counter++;
                    $itemNumber = $counter . '.';
                } elseif ($level === 1) {
                    $itemNumber = toAlphabet($subCounter) . '.';
                }

                $indentClass = $level > 0 ? 'sub-item' : '';

                // Calculate amount
                $rate = floatval($item['rate'] ?? 0);
                $qty = floatval($item['quantity'] ?? 1);
                $itemTotal = $rate * $qty;

                if (!$item['is_header']) {
                    $subtotal += $itemTotal;
                }

                // Build description
                $descriptionText = e($item['description'] ?? '');

                // Check if this item has children
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

                // Display values
                $qtyDisplay = !$item['is_header'] ? number_format($qty, 0) : '';
                $rateDisplay = !$item['is_header'] && $rate ? formatCurrency($rate) : '';
                $totalDisplay = !$item['is_header'] && $itemTotal ? formatCurrency($itemTotal) : '';

                // Render row
                echo '<tr class="item-row ' . $indentClass . '">';
                echo '<td class="col-desc">' . $itemNumber . ' ' . $descriptionText . '</td>';
                // echo '<td class="col-qty">' . $qtyDisplay . '</td>';
                echo '<td class="col-rate">' . $rateDisplay . '</td>';
                echo '<td class="col-total">' . $totalDisplay . '</td>';
                echo '</tr>';

                // Render children
                $childSubCounter = 0;
                foreach ($children as $child) {
                    $childSubCounter++;
                    renderItem($child, $allItems, $level + 1, $counter, $subtotal, $childSubCounter);
                }

                // Add spacing after parent items (only at level 0)
                if ($level === 0) {
                    echo '<tr class="parent-item-end"><td colspan="3"></td></tr>';
                }
            }

            // Initialize counters
            $counter = 0;
            $subtotal = 0;

            // Get root items
            $rootItems = collect($items ?? [])
                ->filter(function ($item) {
                    return empty($item['parent_id']) && empty($item['parent_key']);
                })
                ->sortBy('sort_order');
        @endphp

        <!-- Helper Name -->
        <div class="helper-name">
            Name of Helper Deployed : {{ strtoupper($data['maid_name'] ?? '-') }}
        </div>

        <!-- Items Table -->
        <div class="items-section">
            <table class="items-table">
                <thead>
                    <tr>
                        <th class="col-desc">Item Description</th>
                        {{-- <th class="col-qty">Qty</th> --}}
                        <th class="col-rate">Cost</th>
                        <th class="col-total">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rootItems as $item)
                        @php
                            renderItem($item, $items, 0, $counter, $subtotal, 0);
                        @endphp
                    @endforeach
                </tbody>
            </table>

            <div class="totals-wrapper">
                <div class="total-amount">
                    Total Amount: {{ formatCurrency($subtotal) }}
                </div>
            </div>
        </div>

        <!-- Remarks Section -->
        <div class="remarks-section">
            <div class="remarks-label">Remarks:</div>
            <div class="remarks-box">
                {{ $data['description'] ?? '' }}
            </div>
        </div>

        <!-- Footer -->
        <div class="footer-section">
            <div class="footer-note">
                Paynow to UEN 53496387X or Bank Transfer to DBS Business Multi Currency Account 072-131956-0.<br>
                For further assistance, please contact us at 8785 5651.
            </div>
        </div>
    </div>
</body>

</html>
