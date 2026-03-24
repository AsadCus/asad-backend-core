@extends('layout-report')

@section('document-title', 'Ops Movement PIF - ' . ($opsMovement['package_number'] ?? 'Ops Movement'))

@section('title-bar')
    OPS MOVEMENT - PIF
@endsection

@push('styles')
    <style>
        @page {
            size: A4 portrait;
            margin: 0.2cm 0.35cm;
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
            margin: 7px 0 4px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.25px;
            color: #243644;
            text-transform: uppercase;
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

        $tourLeaders = collect(data_get($opsMovement, 'pif.tour_leaders', []));
        $passengers = collect($opsMovement['passenger_details'] ?? []);
        $flights = collect($opsMovement['flights'] ?? []);
        $accommodations = collect($opsMovement['accommodations'] ?? []);
        $rawdahRows = collect($opsMovement['rawdah_tasreehs'] ?? []);
        $transportRows = collect($opsMovement['transportation_plans'] ?? []);
    @endphp

    {{-- Summary Header --}}
    <table class="summary-grid">
        <tr>
            <th style="width: 20%;">Package Number</th>
            <td style="width: 30%;">{{ $opsMovement['package_number'] ?? '-' }}</td>
            <th style="width: 20%;">Manifest Number</th>
            <td style="width: 30%;">{{ $opsMovement['manifest_number'] ?? '-' }}</td>
        </tr>
        <tr>
            <th>Date Range</th>
            <td>{{ $opsMovement['departure_return_range'] ?? '-' }}</td>
            <th>Visa Type</th>
            <td>{{ $opsMovement['visa_type'] ?? '-' }}</td>
        </tr>
    </table>

    {{-- Passenger Details - Tour Leaders --}}
    <div class="section-title">Passenger Details - Tour Leaders</div>
    <table class="section-table">
        <tr>
            <th style="width: 28%;">Office Type</th>
            <th style="width: 36%;">Official Name</th>
            <th style="width: 36%;">Contact Number</th>
        </tr>
        @forelse ($tourLeaders as $tourLeader)
            <tr>
                <td>{{ $tourLeader['type'] ?? '-' }}</td>
                <td>{{ $tourLeader['name'] ?? '-' }}</td>
                <td>{{ $tourLeader['contact_number'] ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="3" class="text-center">No tour leader data.</td>
            </tr>
        @endforelse
    </table>

    {{-- Passenger Details - Member List --}}
    <div class="section-title">Passenger Details - Member List</div>
    <table class="section-table">
        <tr>
            <th style="width: 5%;" class="text-center">No</th>
            <th style="width: 28%;">Name</th>
            <th style="width: 16%;">Role</th>
            <th style="width: 20%;">Passport Number</th>
            <th style="width: 14%;">Gender</th>
            <th style="width: 17%;" class="text-right">Age</th>
        </tr>
        @forelse ($passengers as $index => $passenger)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $passenger['name'] ?? '-' }}</td>
                <td>{{ $passenger['role'] ?? '-' }}</td>
                <td>{{ $passenger['passport_number'] ?? '-' }}</td>
                <td>{{ $passenger['gender'] ?? '-' }}</td>
                <td class="text-right">{{ (int) ($passenger['age'] ?? 0) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center">No passenger data.</td>
            </tr>
        @endforelse
    </table>

    {{-- Flight Schedule --}}
    <div class="section-title">Flight Schedule</div>
    <table class="section-table">
        <tr>
            <th>Carrier</th>
            <th>From</th>
            <th>To</th>
            <th>Date</th>
            <th>ETD</th>
            <th>ETA</th>
            <th>Remarks</th>
        </tr>
        @forelse ($flights as $flight)
            <tr>
                <td>{{ $flight['airline'] ?? '-' }}</td>
                <td>{{ $flight['from'] ?? '-' }}</td>
                <td>{{ $flight['to'] ?? '-' }}</td>
                <td>{{ $flight['flight_date'] ?? '-' }}</td>
                <td>{{ $flight['departure_datetime'] ?? '-' }}</td>
                <td>{{ $flight['arrival_datetime'] ?? '-' }}</td>
                <td>{{ $flight['remarks'] ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center">No flight schedule data.</td>
            </tr>
        @endforelse
    </table>

    {{-- Accommodation --}}
    <div class="section-title">Accommodation</div>
    <table class="section-table">
        <tr>
            <th>City</th>
            <th>Hotel Name</th>
            <th>Check In</th>
            <th>Check Out</th>
            <th class="text-right">Nights</th>
            <th class="text-right">DBL</th>
            <th class="text-right">TRP</th>
            <th class="text-right">Quad</th>
            <th>Remarks</th>
        </tr>
        @forelse ($accommodations as $accommodation)
            <tr>
                <td>{{ $accommodation['location'] ?? '-' }}</td>
                <td>{{ $accommodation['hotel_name'] ?? '-' }}</td>
                <td>{{ $accommodation['check_in'] ?? '-' }}</td>
                <td>{{ $accommodation['check_out'] ?? '-' }}</td>
                <td class="text-right">{{ (int) ($accommodation['nights'] ?? 0) }}</td>
                <td class="text-right">{{ (int) data_get($accommodation, 'room_counts.double', 0) }}</td>
                <td class="text-right">{{ (int) data_get($accommodation, 'room_counts.triple', 0) }}</td>
                <td class="text-right">{{ (int) data_get($accommodation, 'room_counts.quad', 0) }}</td>
                <td>{{ $accommodation['remarks'] ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="text-center">No accommodation data.</td>
            </tr>
        @endforelse
    </table>

    {{-- Rawdah Tasreeh --}}
    <div class="section-title">Rawdah Tasreeh</div>
    <table class="section-table">
        <tr>
            <th>Date</th>
            <th class="text-right">Women Pax</th>
            <th>Women Time</th>
            <th class="text-right">Men Pax</th>
            <th>Men Time</th>
            <th class="text-right">Total</th>
            <th>Remarks</th>
        </tr>
        @forelse ($rawdahRows as $row)
            @php
                $totalPax = (int) ($row['women_passengers'] ?? 0) + (int) ($row['men_passengers'] ?? 0);
            @endphp
            <tr>
                <td>{{ $row['date'] ?? '-' }}</td>
                <td class="text-right">{{ (int) ($row['women_passengers'] ?? 0) }}</td>
                <td>{{ $row['women_time'] ?? '-' }}</td>
                <td class="text-right">{{ (int) ($row['men_passengers'] ?? 0) }}</td>
                <td>{{ $row['men_time'] ?? '-' }}</td>
                <td class="text-right">{{ $totalPax }}</td>
                <td>{{ $row['remarks'] ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center">No rawdah tasreeh data.</td>
            </tr>
        @endforelse
    </table>

    {{-- Transportation Plan --}}
    <div class="section-title">Transportation Plan</div>
    <table class="section-table">
        <tr>
            <th style="width: 5%;" class="text-center">No</th>
            <th>From</th>
            <th>To</th>
            <th>Date</th>
            <th>Time</th>
            <th>Remarks</th>
        </tr>
        @forelse ($transportRows as $index => $row)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $row['from'] ?? '-' }}</td>
                <td>{{ $row['to'] ?? '-' }}</td>
                <td>{{ $row['travel_date'] ?? '-' }}</td>
                <td>{{ $row['travel_time'] ?? '-' }}</td>
                <td>{{ $row['remarks'] ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center">No transportation plan data.</td>
            </tr>
        @endforelse
    </table>

    <div class="footer-section">
        @if (!empty($branding['footer_text']))
            <div class="footer-note">{!! nl2br(e($branding['footer_text'])) !!}</div>
        @endif

        @include('partials.report-signature-stamp')
    </div>
@endsection
