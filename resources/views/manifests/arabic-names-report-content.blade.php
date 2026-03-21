@extends('layout-report')

@section('document-title', 'Manifest Arabic Names - ' . ($manifest['manifest_number'] ?? 'Manifest'))

@section('title-bar')
    Manifest Arabic Names
@endsection

@push('styles')
    <style>
        @page {
            size: A4 landscape;
            margin: 1cm 1.2cm;
        }

        .summary-grid {
            width: 100%;
            margin-bottom: 10px;
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
            margin-top: 8px;
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
            text-align: right;
            font-size: 11px;
            font-family: DejaVu Sans, sans-serif;
        }

        .muted-note {
            margin-top: 10px;
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
            <td class="summary-label">Generated Date</td>
            <td>{{ now()->translatedFormat('d F Y H:i') }}</td>
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
                    <td class="col-arabic arabic-cell">{{ $member['arabic_name'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No manifest members found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="muted-note">
        Generated on {{ now()->translatedFormat('d F Y H:i') }}.
    </p>
@endsection
