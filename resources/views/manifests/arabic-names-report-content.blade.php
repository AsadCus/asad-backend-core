@extends('layout-report')

@section('document-title', 'Manifest Arabic Names - ' . ($manifest['manifest_number'] ?? 'Manifest'))

@section('title-bar')
    Manifest Arabic Names
@endsection

@push('styles')
    @php
        $marginPreset = $branding['page_margin_preset'] ?? 'normal';
        $resolvedMargin = [
            'narrow' => ['top' => '0.56cm', 'right' => '0.50cm', 'bottom' => '0.56cm', 'left' => '0.50cm'],
            'normal' => ['top' => '0.85cm', 'right' => '0.75cm', 'bottom' => '0.85cm', 'left' => '0.75cm'],
            'wide' => ['top' => '1.70cm', 'right' => '1.50cm', 'bottom' => '1.70cm', 'left' => '1.50cm'],
        ][$marginPreset] ?? ['top' => '0.85cm', 'right' => '0.75cm', 'bottom' => '0.85cm', 'left' => '0.75cm'];

        $sectionSpacingPreset = $branding['section_spacing_preset'] ?? 'normal';
        $moduleSpacing = [
            'compact' => ['block' => '8px', 'table_top' => '6px'],
            'normal' => ['block' => '10px', 'table_top' => '8px'],
            'relaxed' => ['block' => '16px', 'table_top' => '12px'],
        ][$sectionSpacingPreset] ?? ['block' => '10px', 'table_top' => '8px'];
    @endphp
    <style>
        @page {
            size: A4;
            margin-top: {{ $resolvedMargin['top'] }};
            margin-right: {{ $resolvedMargin['right'] }};
            margin-bottom: {{ $resolvedMargin['bottom'] }};
            margin-left: {{ $resolvedMargin['left'] }};
        }

        .summary-grid {
            width: 100%;
            margin-bottom: {{ $moduleSpacing['block'] }};
            border-collapse: collapse;
            table-layout: fixed;
        }

        .summary-grid td {
            border: 1px solid #d7dde3;
            padding: 6px 8px;
            font-size: 9px;
            width: 25%;
        }

        .summary-label {
            font-weight: 700;
            background-color: #f3f7fa;
        }

        .names-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: {{ $moduleSpacing['table_top'] }};
        }

        .names-table th,
        .names-table td {
            border: 1px solid #d7dde3;
            padding: 6px 8px;
            font-size: 10px;
            vertical-align: middle;
        }

        .names-table th {
            background-color: #f4f8fb;
            font-weight: 700;
            text-align: left;
        }

        .names-table th.col-no,
        .names-table td.col-no {
            width: 7%;
            text-align: center;
        }

        .names-table th.col-name,
        .names-table td.col-name {
            width: 42%;
        }

        .names-table th.col-arabic,
        .names-table td.col-arabic {
            width: 51%;
        }

        .arabic-cell {
            direction: rtl;
            unicode-bidi: isolate;
            text-align: right;
            font-size: 11px;
            font-family: DejaVu Sans, sans-serif;
            white-space: pre-wrap;
        }

        .muted-note {
            margin-top: {{ $moduleSpacing['block'] }};
            font-size: 8px;
            color: #687583;
        }
    </style>
@endpush

@section('report-content')
    @php
        $members = collect($manifest['members'] ?? [])
            ->filter(function ($member) {
                return ($member['status'] ?? null) !== 'cancelled' && empty($member['package_official_id']);
            })
            ->values();
    @endphp

    <table class="summary-grid">
        <tr>
            <td class="summary-label">Departure Date</td>
            <td>{{ $manifest['departure_date'] ?? '-' }}</td>
            <td class="summary-label">Package</td>
            <td>{{ $manifest['package_name'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="summary-label">Official In Charge</td>
            <td>{{ $manifest['in_charge_official_name'] ?? '-' }}</td>
            <td class="summary-label">Manifest Number</td>
            <td>{{ $manifest['manifest_number'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="summary-label">Contact Number</td>
            <td>{{ $manifest['in_charge_official_contact_number'] ?? '-' }}</td>
            <td class="summary-label">Package Number</td>
            <td>{{ $manifest['package_number'] ?? '-' }}</td>
        </tr>
    </table>

    <table class="names-table">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th class="col-name">Name</th>
                <th class="col-arabic">Arabic Name</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($members as $index => $member)
                <tr>
                    <td class="col-no">{{ $index + 1 }}</td>
                    <td class="col-name">{{ $member['name_as_per_passport'] ?? '-' }}</td>
                    <td class="col-arabic arabic-cell" dir="rtl" lang="ar">{{ $member['arabic_name'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No manifest members found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer-section">
        @if (!empty($manifest['notes']))
            <div class="footer-note">{!! nl2br(e((string) $manifest['notes'])) !!}</div>
        @elseif (!empty($branding['footer_text']))
            <div class="footer-note">{!! nl2br(e($branding['footer_text'])) !!}</div>
        @endif

        @include('partials.report-signature-stamp')
    </div>
@endsection
