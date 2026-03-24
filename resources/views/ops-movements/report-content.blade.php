@extends('layout-report')

@section('document-title', 'Ops Movement - ' . ($opsMovement['package_number'] ?? 'Ops Movement'))

@section('title-bar')
    OPS MOVEMENT
@endsection

@section('body-class', 'is-landscape')

@push('styles')
    <style>
        @page {
            size: A4 landscape;
            margin: 0.15cm 0.25cm;
        }

        .summary-grid,
        .section-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 8px;
        }

        .summary-grid th,
        .summary-grid td,
        .section-table th,
        .section-table td {
            border: 1px solid #d7dde3;
            padding: 5px 6px;
            font-size: 9px;
            vertical-align: top;
            text-align: left;
            word-break: break-word;
        }

        .summary-grid th,
        .section-table th {
            background: #f4f8fb;
            font-weight: 700;
        }

        .section-title {
            margin: 8px 0 4px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
            color: #22313f;
        }

        .text-center {
            text-align: center !important;
        }

        .text-right {
            text-align: right !important;
        }

        .footer-section {
            margin-top: 8px;
            font-size: 11px;
        }

        .footer-note {
            margin-bottom: 6px;
        }
    </style>
@endpush

@section('report-content')
    @php
        $opsMovement = is_array($opsMovement ?? null) ? $opsMovement : [];
        $passengers = $opsMovement['passengers'] ?? [];
        $accommodations = collect($opsMovement['accommodations'] ?? []);
        $officials = collect($opsMovement['officials'] ?? []);
        $flights = collect($opsMovement['flights'] ?? []);
    @endphp

    {{-- Summary Header --}}
    <table class="summary-grid">
        <tr>
            <th style="width: 12%;">Package Number</th>
            <td style="width: 21%;">{{ $opsMovement['package_number'] ?? '-' }}</td>
            <th style="width: 12%;">Manifest Number</th>
            <td style="width: 21%;">{{ $opsMovement['manifest_number'] ?? '-' }}</td>
            <th style="width: 12%;">Ops Movement Number</th>
            <td style="width: 22%;">{{ $opsMovement['ops_movement_number'] ?? '-' }}</td>
        </tr>
        <tr>
            <th>Package Name</th>
            <td>{{ $opsMovement['name'] ?? '-' }}</td>
            <th>Date Range</th>
            <td>{{ $opsMovement['departure_return_range'] ?? '-' }}</td>
            <th>Visa Type</th>
            <td>{{ $opsMovement['visa_type'] ?? '-' }}</td>
        </tr>
        <tr>
            <th>First Hotel</th>
            <td>{{ $opsMovement['first_hotel_name'] ?? '-' }}</td>
            <th>Ops Base</th>
            <td>{{ $opsMovement['ops_base'] ?? '-' }}</td>
            <th>Infotech Ref</th>
            <td>{{ $opsMovement['infotech_ref'] ?? '-' }}</td>
        </tr>
    </table>

    {{-- PAX / Passengers Summary --}}
    <div class="section-title">PAX / PASSENGERS SUMMARY</div>
    <table class="section-table">
        <tr>
            <th>Adult (M/F)</th>
            <th>Child (B/G)</th>
            <th>Official Total</th>
            <th>Wheelchair (Non-Official)</th>
            <th>Grand Total</th>
        </tr>
        <tr>
            <td>
                {{ (int) ($passengers['adult_total'] ?? 0) }}
                ({{ (int) ($passengers['adult_male'] ?? 0) }}/{{ (int) ($passengers['adult_female'] ?? 0) }})
            </td>
            <td>
                {{ (int) ($passengers['child_total'] ?? 0) }}
                ({{ (int) ($passengers['child_boy'] ?? 0) }}/{{ (int) ($passengers['child_girl'] ?? 0) }})
            </td>
            <td>{{ (int) ($passengers['official_total'] ?? 0) }}</td>
            <td>{{ (int) ($passengers['wheelchair_non_official_total'] ?? 0) }}</td>
            <td>{{ (int) ($passengers['grand_total'] ?? 0) }}</td>
        </tr>
    </table>

    {{-- Accommodation --}}
    <div class="section-title">ACCOMMODATION</div>
    <table class="section-table">
        <tr>
            <th>Location</th>
            <th>Hotel</th>
            <th>Check In</th>
            <th>Check Out</th>
            <th>Meal</th>
            <th>IC</th>
        </tr>
        @forelse ($accommodations as $accommodation)
            <tr>
                <td>{{ $accommodation['location'] ?? '-' }}</td>
                <td>{{ $accommodation['hotel_name'] ?? '-' }}</td>
                <td>{{ $accommodation['check_in'] ?? '-' }}</td>
                <td>{{ $accommodation['check_out'] ?? '-' }}</td>
                <td>{{ $accommodation['type_of_meal'] ?? '-' }}</td>
                <td>{{ $accommodation['ic'] ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center">No accommodation data.</td>
            </tr>
        @endforelse
    </table>

    {{-- Officials --}}
    <div class="section-title">OFFICIALS</div>
    <table class="section-table">
        <tr>
            <th>Name</th>
            <th>Hotel</th>
        </tr>
        @forelse ($officials as $official)
            <tr>
                <td>{{ $official['name'] ?? '-' }}</td>
                <td>{{ $official['hotel'] ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="2" class="text-center">No official data.</td>
            </tr>
        @endforelse
    </table>

    {{-- Flight Ticket --}}
    <div class="section-title">FLIGHT TICKET</div>
    <table class="section-table">
        <tr>
            <th>Description</th>
            <th>From</th>
            <th>To</th>
            <th>Departure</th>
            <th>Arrival</th>
            <th>Airline</th>
            <th>PNR</th>
            <th>DOA By</th>
            <th>DOA Datetime</th>
            <th>IC</th>
        </tr>
        @forelse ($flights as $flight)
            <tr>
                <td>{{ $flight['description'] ?? '-' }}</td>
                <td>{{ $flight['from'] ?? '-' }}</td>
                <td>{{ $flight['to'] ?? '-' }}</td>
                <td>{{ $flight['departure_datetime'] ?? '-' }}</td>
                <td>{{ $flight['arrival_datetime'] ?? '-' }}</td>
                <td>{{ $flight['airline'] ?? '-' }}</td>
                <td>{{ $flight['pnr'] ?? '-' }}</td>
                <td>{{ $flight['doa_by'] ?? '-' }}</td>
                <td>{{ $flight['doa_datetime'] ?? '-' }}</td>
                <td>{{ $flight['ic'] ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="10" class="text-center">No flight data.</td>
            </tr>
        @endforelse
    </table>

    {{-- Vehicle / Train --}}
    <div class="section-title">VEHICLE / TRAIN</div>
    <table class="section-table">
        <tr>
            <th>Vehicle Type</th>
            <th>Driver Name</th>
            <th>Driver Contact</th>
            <th>Train Description</th>
        </tr>
        <tr>
            <td>{{ $opsMovement['vehicle_type'] ?? '-' }}</td>
            <td>{{ $opsMovement['vehicle_driver_name'] ?? '-' }}</td>
            <td>{{ $opsMovement['vehicle_driver_contact_number'] ?? '-' }}</td>
            <td>{{ $opsMovement['train_description'] ?? '-' }}</td>
        </tr>
    </table>

    <div class="footer-section">
        @if (!empty($branding['footer_text']))
            <div class="footer-note">{!! nl2br(e($branding['footer_text'])) !!}</div>
        @endif

        @include('partials.report-signature-stamp')
    </div>
@endsection
