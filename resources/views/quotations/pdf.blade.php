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

        /* Fix: explicit px dimensions, no object-fit (not supported in PDF renderers) */
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

        /* ── Divider ── */
        .section-divider {
            border: none;
            border-top: 1px solid #d0d0d0;
            margin: 10px 0;
        }

        /* ── Order Info ── */
        .order-info-section {
            padding: 0 30px;
            margin-bottom: 12px;
        }

        .order-info {
            width: 100%;
            border-collapse: collapse;
        }

        .order-info td {
            vertical-align: top;
            padding: 1px 0;
            font-size: 10px;
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
            padding: 0 30px;
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
            padding: 6px 0;
        }

        .items-table td {
            padding: 3px 0;
            vertical-align: top;
            font-size: 10px;
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
            font-size: 10px;
        }

        .item-row {
            border-bottom: 1px solid #ebebeb;
        }

        .item-row.root .col-desc {
            font-weight: bold;
        }

        /* ── Totals ── */
        .totals-wrapper {
            text-align: right;
            padding: 6px 0 4px;
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
            padding: 12px 30px 0;
            font-size: 9px;
        }

        .footer-note {
            text-align: center;
            margin-bottom: 8px;
            line-height: 1.5;
            color: #333;
        }

        .replacement-box {
            text-align: center;
            font-weight: bold;
            margin: 10px 0;
            font-size: 10px;
            border: 1px solid #333;
            padding: 4px;
            display: inline-block;
            width: 100%;
        }

        .terms-note {
            text-align: center;
            font-size: 8.5px;
            line-height: 1.5;
            color: #555;
        }

        .updated-date {
            text-align: right;
            font-weight: bold;
            font-size: 9px;
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
                    @if ($data['sales_registration_number'])
                        REGISTRATION NO. {{ $data['sales_registration_number'] }}&nbsp;&nbsp;
                    @endif
                    LICENCE NO. 25C2708
                </div>
            </td>
        </tr>
    </table>

    {{-- ── TITLE BAR ── --}}
    <div class="title-bar">QUOTATION</div>

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
                            <td>{!! $data['customer_address'] ?? '-' !!}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Description</td>
                            <td class="sep">:</td>
                            <td>{{ $data['description'] ?? 'New / Fresh Helper' }}</td>
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
            @forelse($data['notes'] ?? [] as $note)
                <div class="footer-note">{!! $note['description'] ?? '' !!}</div>
            @empty
                <div class="footer-note">
                    50% refund of Service Fee within 6 months if employer decided to terminate the contract &amp; MDW
                    must
                    return to agency for Transfer (Employer to sign/authorise the consent of transfer online)
                </div>
                <div class="replacement-box">2 Free Replacements within 6 months</div>
                <div class="terms-note">
                    For every replacement, the employer will need to pay: Top Up difference in Agency Fee + Processing
                    Fee +
                    Documentation Fee + WPOL Filing Fee + SIP (if needed) + Transport &amp; Facilitation Fee + Insurance
                    Fee +
                    Any Placement Fee Top Up + Embassy Contract Fee (if needed) * Loan/Placement Fee (Upfront Payment)
                </div>
            @endforelse
        @endif

        {{-- Stamp & Signature --}}
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

</body>

</html>
