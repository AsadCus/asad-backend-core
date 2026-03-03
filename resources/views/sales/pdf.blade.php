<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Sales Profile - {{ $data['name'] ?? 'Sales' }}</title>
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
            font-size: 10px;
            line-height: 1.5;
        }

        .logo-cell img {
            max-width: 320px;
            max-height: 160px;
            height: auto;
            width: auto;
            display: block;
        }

        .info-cell b {
            font-size: 11px;
        }

        /* Title Bar */
        .title-bar {
            background-color: {{ $branding['title_color'] ?? '#40A09D' }};
            color: white;
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            padding: 6px;
            letter-spacing: 3px;
            margin-bottom: 20px;
        }

        /* Content */
        .content-wrapper {
            padding: 0 20px;
        }

        /* Info Table */
        .info-section {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .info-section td {
            padding: 8px 10px;
            border: 1px solid #ddd;
            vertical-align: top;
        }

        .info-section .label-cell {
            width: 35%;
            font-weight: bold;
            background-color: #f5f5f5;
            white-space: nowrap;
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
                @if(!empty($branding['logo_path_absolute']) && file_exists($branding['logo_path_absolute']))
                    <img src="{{ $branding['logo_path_absolute'] }}" alt="Company Logo">
                @else
                    <img src="{{ public_path('logo_agency.png') }}" alt="Company Logo">
                @endif
            </td>
            <td class="info-cell">
                <div>
                    <b style="margin-bottom: 4px; font-size: 13px; color: #333;">{{ $branding['company_name'] ?? 'Urban Care Employment Agency' }}</b><br>
                    {!! nl2br(e($branding['company_address'] ?? "931 Yishun Central 1\n#01-109, Singapore 760931")) !!}<br>
                    @if (!empty($branding['company_phone']))
                        <div style="margin-top: 2px;">Tel: {{ $branding['company_phone'] }}</div>
                    @endif
                    @if (!empty($branding['company_email']))
                        <div>Email: {{ $branding['company_email'] }}</div>
                    @endif
                    <div style="margin-top: 4px;">
                        <b>LICENCE NO. 25C2708</b>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Title Bar -->
    <div class="title-bar">SALES PROFILE</div>

    <!-- Salesperson Details -->
    <div class="content-wrapper">
        <table class="info-section">
            <tr>
                <td class="label-cell">Name</td>
                <td>{{ $data['name'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label-cell">Email</td>
                <td>{{ $data['email'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label-cell">Contact</td>
                <td>{{ $data['contact'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label-cell">Branch</td>
                <td>{{ $data['branch_name'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label-cell">Registration Number</td>
                <td>{{ $data['registration_number'] ?? '-' }}</td>
            </tr>
        </table>

        <!-- Footer -->
        <div class="footer-section">
            @if(!empty($branding['footer_text']))
                <div class="footer-note">{!! nl2br(e($branding['footer_text'])) !!}</div>
            @endif

            @if(!empty($branding['show_stamp']) && !empty($branding['stamp_path_absolute']) && file_exists($branding['stamp_path_absolute']))
                <div style="margin-top: 15px;">
                    <img src="{{ $branding['stamp_path_absolute'] }}" alt="Company Stamp" style="max-height: 80px; width: auto;">
                </div>
            @endif

            @if(!empty($branding['show_signature']) && !empty($branding['signature_path_absolute']) && file_exists($branding['signature_path_absolute']))
                <div style="margin-top: 10px;">
                    <p style="font-size: 9px; margin: 0;">Authorised Signature</p>
                    <img src="{{ $branding['signature_path_absolute'] }}" alt="Authorised Signature" style="max-height: 60px; width: auto;">
                </div>
            @endif
        </div>
    </div>
</body>

</html>
