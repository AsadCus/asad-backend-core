@extends('layout-report')

@section('document-title', 'Namelist Course & Collection Items - ' . ($manifest['manifest_number'] ?? 'Manifest'))

@section('title-bar')
    Namelist Course & Collection Items
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
        }

        .summary-grid td {
            border: 1px solid #d7dde3;
            padding: 6px 8px;
            font-size: 9px;
        }

        .summary-label {
            width: 16%;
            font-weight: 700;
            background-color: #f3f7fa;
        }

        .collection-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 8px;
        }

        .collection-table th,
        .collection-table td {
            border: 1px solid #d7dde3;
            padding: 6px 4px;
            font-size: 9px;
            text-align: center;
            vertical-align: middle;
        }

        .collection-table th {
            background-color: #f4f8fb;
            font-weight: 700;
            line-height: 1.3;
        }

        .collection-table td.name-col,
        .collection-table th.name-col {
            text-align: left;
            padding-left: 8px;
            width: 22%;
        }

        .collection-table td.sn-col,
        .collection-table th.sn-col {
            width: 4.5%;
        }

        .check-mark {
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            color: #0f4d5a;
        }

        .empty-mark {
            color: #97a5b2;
            font-size: 10px;
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
        $travelers = collect($manifest['travelers'] ?? [])
            ->filter(function ($traveler) {
                return ($traveler['status'] ?? null) !== 'cancelled';
            })
            ->values();
    @endphp

    <table class="summary-grid">
        <tr>
            <td class="summary-label">Manifest Number</td>
            <td>{{ $manifest['manifest_number'] ?? '-' }}</td>
            <td class="summary-label">Package</td>
            <td>{{ $manifest['package_name'] ?? '-' }}</td>
            <td class="summary-label">Status</td>
            <td>{{ ucfirst((string) ($manifest['status'] ?? 'draft')) }}</td>
        </tr>
    </table>

    <table class="collection-table">
        <thead>
            <tr>
                <th class="sn-col">S/N</th>
                <th class="name-col">Name as per passport</th>
                <th>Course 1</th>
                <th>Course 2</th>
                <th>Lanyard</th>
                <th>Luggage Tag</th>
                <th>Cabin Tag</th>
                <th>Passport Cover</th>
                <th>Umrah Guidebook</th>
                <th>Sling Bag</th>
                <th>Cabin Size Luggage</th>
                <th>Umrah Essentials</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($travelers as $index => $traveler)
                <tr>
                    <td class="sn-col">{{ $index + 1 }}</td>
                    <td class="name-col">{{ $traveler['name_as_per_passport'] ?? '-' }}</td>
                    @foreach (['course_1', 'course_2', 'lanyard', 'luggage_tag', 'cabin_tag', 'passport_cover', 'umrah_guidebook', 'sling_bag', 'cabin_size_luggage', 'umrah_essentials'] as $field)
                        <td>
                            @if (!empty($traveler[$field]))
                                <span class="check-mark">&#10003;</span>
                            @else
                                <span class="empty-mark">-</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="12">No manifest members found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="muted-note">
        Generated on {{ now()->translatedFormat('d F Y H:i') }}.
    </p>
@endsection
