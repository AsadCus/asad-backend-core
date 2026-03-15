<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>@yield('document-title', 'Report')</title>
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

        @@media screen {
            html {
                background: #cacaca;
            }

            body {
                font-size: 11px;
                line-height: 1.55;
                padding: 1.5cm 1.8cm;
                background: #ffffff;
                max-width: 794px;
                min-height: 27.7cm;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.18);
            }

            /* Prevent browsers from styling links */
            a {
                color: inherit;
                text-decoration: none;
            }

            /* Scale up text that was sized for 96dpi DomPDF */
            .company-name {
                font-size: 13px;
            }

            .company-details {
                font-size: 10px;
            }

            .company-reg {
                font-size: 10px;
            }

            .title-bar {
                font-size: 14px;
                padding: 7px 0;
                letter-spacing: 5px;
            }

            .footer-section {
                font-size: 10px;
            }

            .footer-note {
                font-size: 10px;
            }

            .updated-date {
                font-size: 10px;
            }
        }
    </style>

    @stack('styles')
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
                    @yield('extra-company-reg')
                    LICENCE NO. 25C2708
                </div>
            </td>
        </tr>
    </table>

    {{-- ── TITLE BAR ── --}}
    <div class="title-bar">@yield('title-bar')</div>

    {{-- ── MAIN CONTENT ── --}}
    @yield('report-content')

</body>

</html>
