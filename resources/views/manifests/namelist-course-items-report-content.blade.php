@extends('layout-report')

@section('document-title', 'Manifest Namelist Course Items - ' . ($manifest['manifest_number'] ?? 'Manifest'))

@section('title-bar')
    Manifest Namelist Course Items
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

        .summary-head {
            text-align: center;
        }

        .collection-table {
            width: 100%;
            border-collapse: collapse;
            /* table-layout: fixed; */
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
            width: 200px;
        }

        .collection-table td.sn-col,
        .collection-table th.sn-col {
            width: 4.5%;
        }

        .print-checkbox {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 13px;
            height: 13px;
            border: 1px solid #8ea1b2;
            border-radius: 2px;
            line-height: 1;
            font-size: 10px;
            font-weight: 700;
            color: #0f4d5a;
            background: #fff;
        }

        .print-checkbox.checked {
            border-color: #0f4d5a;
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
                return ($traveler['status'] ?? null) !== 'cancelled' && empty($traveler['package_official_id']);
            })
            ->values();

        $accommodations = collect($manifest['package_accommodations'] ?? [])->values();
    @endphp

    <table class="summary-grid">
        <tr>
            <td class="summary-label summary-head" colspan="2">Embarkation Details</td>
            <td class="summary-label summary-head" colspan="2">Details</td>
        </tr>
        <tr>
            <td class="summary-label">Date of Departure</td>
            <td>{{ $manifest['departure_date'] ?? '-' }}</td>
            <td class="summary-label">Manifest Number</td>
            <td>{{ $manifest['manifest_number'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="summary-label">Date of Return</td>
            <td>{{ $manifest['return_date'] ?? '-' }}</td>
            <td class="summary-label">Package Number</td>
            <td>{{ $manifest['package_number'] ?? '-' }}</td>
        </tr>
        @forelse ($accommodations as $index => $accommodation)
            <tr>
                <td class="summary-label">
                    Date of Enter {{ $accommodation['location'] ?? '-' }}
                    @if (!empty($accommodation['hotel_name']))
                        ({{ $accommodation['hotel_name'] }})
                    @endif
                </td>
                <td>{{ $accommodation['check_in_formatted'] ?? '-' }}</td>
                @if ($index === 0)
                    <td class="summary-label">Package</td>
                    <td>{{ $manifest['package_name'] ?? '-' }}</td>
                @else
                    <td class="summary-label">&nbsp;</td>
                    <td>&nbsp;</td>
                @endif
            </tr>
        @empty
            <tr>
                <td class="summary-label">Date of Enter</td>
                <td>-</td>
                <td class="summary-label">Package</td>
                <td>{{ $manifest['package_name'] ?? '-' }}</td>
            </tr>
        @endforelse
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
                            <span class="print-checkbox {{ !empty($traveler[$field]) ? 'checked' : '' }}">
                                {{ !empty($traveler[$field]) ? 'X' : '' }}
                            </span>
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
