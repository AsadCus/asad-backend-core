<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
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
    <title>Quotation</title>
    <style>
        @page {
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

        /* Quotation Bar */
        .quotation-bar {
            background-color: #40A09D;
            color: white;
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            padding: 6px;
            letter-spacing: 3px;
            margin-bottom: 15px;
        }

        /* Order Info */
        .order-info-section {
            padding: 0 40px;
            margin-bottom: 15px;
        }

        .order-info {
            width: 100%;
            margin-bottom: 15px;
        }

        .order-info td {
            vertical-align: top;
            padding: 0px;
        }

        /* Content Wrapper */
        .content-wrapper {
            padding: 0 40px;
        }

        /* Main Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 10px 0;
        }

        .items-table td {
            padding: 4px 0;
            vertical-align: top;
        }

        .col-desc {
            width: auto;
        }

        .col-price {
            width: auto;
            text-align: right;
        }

        /* Indentation Logic */

        .sub-item .col-desc {
            padding-left: 24px;
        }

        /* Header rows */
        .header-row {
            border-top: 1px solid #dee2e6;
            border-bottom: 1px solid #dee2e6;
        }

        .header-row td {
            font-weight: bold;
            padding: 4px 0;
        }

        .item-row {
            border-bottom: 1px solid #e9ecef;
        }

        .item-row.root .col-desc {
            font-weight: bold;
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

        /* Footer */
        .footer-section {
            clear: both;
            padding: 15px 40px 0;
            font-size: 10px;
        }

        .footer-note {
            text-align: center;
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .replacement-box {
            text-align: center;
            font-weight: bold;
            margin: 15px 0;
        }

        .terms-note {
            text-align: center;
            font-size: 9px;
            line-height: 1.4;
        }

        .updated-date {
            text-align: center;
            font-weight: bold;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                <img src="{{ $branding['logo_url'] ?? public_path('logo_agency.png') }}" alt="Company Logo">
            </td>
            <td class="info-cell">
                <div>
                    <b style="margin-bottom: 4px; font-size: 13px; color: #333;">{{ $branding['company_name'] ?? 'Urban Care Employment Agency' }}</b><br>
                    {!! nl2br(e($branding['company_address'] ?? "931 Yishun Central 1\n#01-109, Singapore 760931")) !!}<br>
                    <div style="margin-top: 4px;">
                        @if ($data['sales_registration_number'])
                            <b>REGISTRATION NO. {{ $data['sales_registration_number'] }}</b><br>
                        @endif
                        <b>LICENCE NO. 25C2708</b>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="quotation-bar">QUOTATION</div>

    <div class="order-info-section">
        <table class="order-info">
            <tr>
                <td width="65%">
                    <table>
                        <tr>
                            <td style="white-space: nowrap;"><b>Name</b></td>
                            <td>: {{ $data['customer_name'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td style="white-space: nowrap; vertical-align: top;"><b>Address</b></td>
                            <td style="vertical-align: top;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <tr>
                                        <td style="width: fit-content; vertical-align: top; padding: 0;">:</td>
                                        <td style="vertical-align: top; padding: 0;">{!! $data['customer_address'] ?? '-' !!}</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="white-space: nowrap;"><b>Description</b></td>
                            <td>: {{ $data['description'] ?? 'New / Fresh Helper' }}</td>
                        </tr>
                    </table>
                </td>
                <td width="35%">
                    <table>
                        <tr>
                            <td style="white-space: nowrap;"><b>Quotation Number</b></td>
                            <td>: {{ $data['quotation_number'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td style="white-space: nowrap;"><b>Placement Fee</b></td>
                            <td>: {{ $data['payment_plan_label'] ?? '-' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <div class="content-wrapper">
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

                        $isPlacement = !empty($item['is_placement_fee']);

                        $amount = null;
                        if (empty($item['is_header'])) {
                            $amount = $isPlacement
                                ? (float) ($data['monthly_salary'] ?? 0) * (float) ($data['loan_duration'] ?? 0)
                                : (float) ($item['quantity'] ?? 0) * (float) ($item['rate'] ?? 0);

                            $subtotal += $amount;
                        }

                        $description = $item['description'];

                        if ($isPlacement && !empty($data['monthly_salary']) && !empty($data['loan_duration'])) {
                            $description .=
                                ' - $' .
                                number_format($data['monthly_salary'], 0) .
                                ' x ' .
                                $data['loan_duration'] .
                                ' month(s)';
                        }
                    @endphp

                    @if (!empty($item['is_header']))
                        <tr class="header-row">
                            <td colspan="3">
                                {{ $label }} {{ $description }}
                            </td>
                        </tr>
                    @else
                        <tr class="item-row {{ $isRoot ? 'root' : '' }} {{ $indentClass }}">
                            <td colspan="2" class="col-desc">{{ $label }} {{ $description }}</td>
                            <td colspan="1" class="col-price">{{ formatCurrency($amount) }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>

        <div class="totals-wrapper">
            <div class="total-amount">
                Total Amount: {{ formatCurrency($subtotal) }}
            </div>
        </div>
    </div>

    <!-- Footer Notes -->
    <div class="footer-section">
        @forelse($data['notes'] ?? [] as $note)
            <div class="footer-note">
                {!! $note['description'] ?? '' !!}
            </div>
        @empty
            <div class="footer-note">
                50 % refund of Service Fee within 6 months if employer decided to terminate the contract & MDW must
                return
                to<br>
                agency for Transfer (Employer to sign/authorise the consent of transfer online)
            </div>

            <div class="replacement-box">2 Free Replacements within 6 months</div>

            <div class="terms-note">
                For every replacement, the employer will need to pay: Top Up difference in Agency Fee + Processing Fee
                +<br>
                Documentation Fee + WPOL Filing Fee + SIP (if needed) + Transport & Facilitation Fee + Insurance Fee +
                Any<br>
                Placement Fee Top Up + Embassy Contract Fee (if needed) * Loan/Placement Fee (Upfront Payment)
            </div>
        @endforelse

        <div class="updated-date">UPDATED: {{ date('d/m/Y') }}</div>
    </div>

</body>

</html>
