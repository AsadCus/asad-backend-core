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
            text-align: right;
            margin-bottom: 6px;
        }
    </style>
@endpush

@section('report-content')
    @php
        $opsMovement = is_array($opsMovement ?? null) ? $opsMovement : [];
        $tourLeaders = collect(data_get($opsMovement, 'pif.tour_leaders', []));
        $flights = collect($opsMovement['flights'] ?? []);
        $accommodations = collect($opsMovement['accommodations'] ?? []);
        $rawdahRows = collect($opsMovement['rawdah_tasreehs'] ?? []);
        $transportRows = collect($opsMovement['transportation_plans'] ?? []);
    @endphp

    {{-- Summary Header --}}
    <table class="summary-grid">
        <tr>
            <th style="width: 20%;">Package No.</th>
            <td style="width: 30%;">{{ $opsMovement['package_number'] ?? '-' }}</td>
            <th style="width: 20%;">Manifest No.</th>
            <td style="width: 30%;">{{ $opsMovement['manifest_number'] ?? '-' }}</td>
        </tr>
        <tr>
            <th>Pax Summary</th>
            <td colspan="3">
                Adults: {{ (int) data_get($opsMovement, 'passengers.adult_total', 0) }} |
                Children: {{ (int) data_get($opsMovement, 'passengers.child_total', 0) }} |
                Officials: {{ (int) data_get($opsMovement, 'passengers.official_total', 0) }}
            </td>
        </tr>
    </table>

    {{-- Tour Leaders Section --}}
    <div class="section-title">Tour Leaders</div>
    <table class="section-table">
        <tr>
            <th style="width: 25%;">Office Type</th>
            <th style="width: 45%;">Official Name</th>
            <th style="width: 30%;">Contact Number</th>
        </tr>
        @forelse ($tourLeaders as $leader)
            <tr>
                <td>{{ $leader['type'] ?? '-' }}</td>
                <td>{{ $leader['name'] ?? '-' }}</td>
                <td>{{ $leader['contact_number'] ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="3" class="text-center">No tour leader data.</td>
            </tr>
        @endforelse
    </table>

    {{-- Flight Schedule --}}
    <div class="section-title">Flight Schedule</div>
    <table class="section-table">
        <tr>
            <th style="width: 12%;">Carrier</th>
            <th style="width: 12%;">From</th>
            <th style="width: 12%;">To</th>
            <th style="width: 18%;">ETD</th>
            <th style="width: 18%;">ETA</th>
            <th>Remarks</th>
        </tr>
        @foreach ($flights as $flight)
            <tr>
                <td>{{ $flight['airline'] ?? '-' }}</td>
                <td>{{ $flight['from'] ?? '-' }}</td>
                <td>{{ $flight['to'] ?? '-' }}</td>
                <td>{{ $flight['departure_datetime'] ?? '-' }}</td>
                <td>{{ $flight['arrival_datetime'] ?? '-' }}</td>
                <td style="font-style: italic; color: #555;">{{ $flight['remarks'] ?? '-' }}</td>
            </tr>
        @endforeach
    </table>

    {{-- Accommodation with Room Counts --}}
    <div class="section-title">Accommodation & Room Allocation</div>
    <table class="section-table">
        <tr>
            <th style="width: 12%;">City</th>
            <th style="width: 18%;">Hotel</th>
            <th style="width: 15%;">Check In/Out</th>
            <th style="width: 5%;" class="text-center">Nights</th>
            <th style="width: 5%;" class="text-center">SGL</th>
            <th style="width: 5%;" class="text-center">DBL</th>
            <th style="width: 5%;" class="text-center">TRP</th>
            <th style="width: 5%;" class="text-center">QUAD</th>
            <th>Remarks</th>
        </tr>
        @foreach ($accommodations as $acc)
            <tr>
                <td>{{ $acc['location'] ?? '-' }}</td>
                <td>{{ $acc['hotel_name'] ?? '-' }}</td>
                <td>{{ $acc['check_in'] }} - {{ $acc['check_out'] }}</td>
                <td class="text-center">{{ $acc['nights'] }}</td>
                <td class="text-center">{{ (int) data_get($acc, 'room_counts.single', 0) }}</td>
                <td class="text-center">{{ (int) data_get($acc, 'room_counts.double', 0) }}</td>
                <td class="text-center">{{ (int) data_get($acc, 'room_counts.triple', 0) }}</td>
                <td class="text-center">{{ (int) data_get($acc, 'room_counts.quad', 0) }}</td>
                <td style="font-size: 8px;">{{ $acc['remarks'] ?? '-' }}</td>
            </tr>
        @endforeach
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
        @if (!empty($opsMovement['notes']))
            <div class="footer-note">{!! nl2br(e((string) $opsMovement['notes'])) !!}</div>
        @elseif (!empty($branding['footer_text']))
            <div class="footer-note">{!! nl2br(e($branding['footer_text'])) !!}</div>
        @endif

        @include('partials.report-signature-stamp')
    </div>
@endsection
