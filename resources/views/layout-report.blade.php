<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>@yield('document-title', 'Report')</title>
    @php
        $marginPreset = $branding['page_margin_preset'] ?? 'normal';
        $marginByPreset = [
            'narrow' => ['top' => '0.56cm', 'right' => '0.50cm', 'bottom' => '0.56cm', 'left' => '0.50cm'],
            'normal' => ['top' => '0.85cm', 'right' => '0.75cm', 'bottom' => '0.85cm', 'left' => '0.75cm'],
            'wide' => ['top' => '1.70cm', 'right' => '1.50cm', 'bottom' => '1.70cm', 'left' => '1.50cm'],
        ];
        $resolvedMargin = $marginByPreset[$marginPreset] ?? $marginByPreset['normal'];
        $pageMarginTop = $resolvedMargin['top'];
        $pageMarginRight = $resolvedMargin['right'];
        $pageMarginBottom = $resolvedMargin['bottom'];
        $pageMarginLeft = $resolvedMargin['left'];
        $pageMargin = implode(' ', [$pageMarginTop, $pageMarginRight, $pageMarginBottom, $pageMarginLeft]);

        $sectionSpacingPreset = $branding['section_spacing_preset'] ?? 'normal';
        $sectionSpacingByPreset = [
            'compact' => ['section' => '8px', 'header' => '8px', 'footer' => '8px'],
            'normal' => ['section' => '10px', 'header' => '10px', 'footer' => '10px'],
            'relaxed' => ['section' => '16px', 'header' => '16px', 'footer' => '16px'],
        ];
        $spacingTokens = $sectionSpacingByPreset[$sectionSpacingPreset] ?? $sectionSpacingByPreset['normal'];
    @endphp
    <style>
        @page {
            size: A4;
            margin-top: {{ $pageMarginTop }};
            margin-right: {{ $pageMarginRight }};
            margin-bottom: {{ $pageMarginBottom }};
            margin-left: {{ $pageMarginLeft }};
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 13px;
            line-height: 1.5;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
        }

        /* ── Header ── */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: {{ $spacingTokens['header'] }};
            page-break-inside: avoid;
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
            height: 84px;
            max-width: 180px;
            margin: 0;
        }

        .company-name {
            font-size: 14px;
            font-weight: bold;
            color: #222;
            margin-bottom: 2px;
            display: block;
        }

        .company-details {
            font-size: 12px;
            color: #444;
            line-height: 1.45;
        }

        .company-reg {
            font-size: 12px;
            font-weight: bold;
            margin-top: 3px;
        }

        /* ── Title Bar ── */
        .title-bar {
            background-color: {{ $branding['title_color'] ?? '#c05427' }};
            color: #fff;
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            padding: 4px 6px;
            letter-spacing: 1px;
            margin-bottom: {{ $spacingTokens['section'] }};
            page-break-inside: avoid;
        }

        .report-content-stack > * {
            margin-bottom: {{ $spacingTokens['section'] }};
        }

        .report-content-stack > *:last-child {
            margin-bottom: 0;
        }

        .report-content-stack > .items-section,
        .report-content-stack > .items-table-wrap,
        .report-content-stack > .items-table {
            margin-bottom: 0;
        }

        /* ── Footer ── */
        .footer-section {
            font-size: 12px;
            padding-top: 6px;
            margin-top: {{ $spacingTokens['footer'] }};
            border-top: 1px solid #d0d0d0;
            page-break-inside: avoid;
        }

        .footer-note {
            text-align: right;
            margin-bottom: 4px;
            line-height: 1.5;
            color: #333;
        }

        /* ── Notes Section (Rich-text notes above footer) ── */
        .report-notes {
            border-top: 1px solid #d0d0d0;
            padding-top: 8px;
            margin-bottom: 8px;
        }

        .report-notes-heading {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #666;
            margin: 0 0 5px;
        }

        .note-item {
            font-size: 11px;
            line-height: 1.55;
            color: #333;
            margin-bottom: 5px;
        }

        .note-item:last-child {
            margin-bottom: 0;
        }

        /* ── Tiptap HTML rendering inside note-item ── */
        .note-item p {
            margin: 0 0 3px;
        }

        .note-item p:last-child {
            margin-bottom: 0;
        }

        .note-item strong { font-weight: bold; }
        .note-item em { font-style: italic; }
        .note-item u { text-decoration: underline; }
        .note-item s { text-decoration: line-through; }

        .note-item ul,
        .note-item ol {
            margin: 2px 0;
            padding-left: 18px;
        }

        .note-item li {
            margin-bottom: 1px;
        }

        .note-item blockquote {
            border-left: 3px solid #ccc;
            margin: 4px 0;
            padding-left: 8px;
            color: #555;
        }


        @if (!($is_pdf ?? false))
        @@media screen {
            html {
                background: #cacaca;
            }

            body {
                font-size: 13px;
                line-height: 1.5;
                padding-top: {{ $pageMarginTop }};
                padding-right: {{ $pageMarginRight }};
                padding-bottom: {{ $pageMarginBottom }};
                padding-left: {{ $pageMarginLeft }};
                background: #ffffff;
                max-width: 794px;
                min-height: 27.7cm;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.18);
            }

            body.is-landscape {
                max-width: 1122px;
                min-height: 21cm;
            }

            /* Prevent browsers from styling links */
            a {
                color: inherit;
                text-decoration: none;
            }

        }
        @endif
    </style>

    @stack('styles')
</head>

<body class="@yield('body-class')">

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
                <span class="company-name">{{ $branding['company_name'] ?? 'Karva Travel & Tours' }}</span>
                <div class="company-details">
                    {!! nl2br(e($branding['company_address'] ?? "390 Victoria Street\nGolden Landmark Shopping Centre\n#03-28 Singapore 188061")) !!}
                    @if (!empty($branding['company_phone']))
                        <br>Tel: {{ $branding['company_phone'] }}
                    @endif
                    @if (!empty($branding['company_email']))
                        <br>Email: {{ $branding['company_email'] }}
                    @endif
                </div>
                <div class="company-reg">
                    @yield('extra-company-reg')
                </div>
            </td>
        </tr>
    </table>

    {{-- ── TITLE BAR ── --}}
    <div class="title-bar">@yield('title-bar')</div>

    {{-- ── MAIN CONTENT ── --}}
    <div class="report-content-stack">
        @yield('report-content')
    </div>

</body>

</html>
