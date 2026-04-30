@extends('layout-report')

@section('document-title', 'Ops Movement PIF - ' . ($opsMovement['package_number'] ?? 'Ops Movement'))

@section('title-bar')
    OPS MOVEMENT - PIF
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
            'compact' => ['block' => '8px', 'title_top' => '10px', 'title_bottom' => '6px'],
            'normal' => ['block' => '10px', 'title_top' => '14px', 'title_bottom' => '6px'],
            'relaxed' => ['block' => '16px', 'title_top' => '20px', 'title_bottom' => '10px'],
        ][$sectionSpacingPreset] ?? ['block' => '10px', 'title_top' => '14px', 'title_bottom' => '6px'];
    @endphp
    <style>
        @page {
            size: A4 portrait;
            margin-top: {{ $resolvedMargin['top'] }};
            margin-right: {{ $resolvedMargin['right'] }};
            margin-bottom: {{ $resolvedMargin['bottom'] }};
            margin-left: {{ $resolvedMargin['left'] }};
        }

        .summary-grid,
        .section-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: {{ $moduleSpacing['block'] }};
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
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: {{ $moduleSpacing['title_top'] }} 0 {{ $moduleSpacing['title_bottom'] }};
            padding: 4px 8px;
            background: #f0f0f0;
            border-left: 3px solid {{ $branding['title_color'] ?? '#40A09D' }};
            color: #222;
        }

        .text-center {
            text-align: center !important;
        }

        .text-right {
            text-align: right !important;
        }

        .group-duration {
            text-align: right;
            font-size: 10px;
            font-weight: 700;
            color: #c0392b;
            margin-bottom: {{ $moduleSpacing['title_bottom'] }};
        }

        .footer-section {
            margin-top: {{ $moduleSpacing['block'] }};
            font-size: 11px;
        }

        .footer-note {
            text-align: right;
            margin-bottom: 6px;
        }

        .legend-text {
            font-size: 8px;
            color: #666;
            font-style: italic;
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

        $adultTotal = (int) data_get($opsMovement, 'passengers.adult_total', 0);
        $childTotal = (int) data_get($opsMovement, 'passengers.child_total', 0);
        $infantTotal = (int) data_get($opsMovement, 'passengers.infant_total', 0);
        $officialTotal = (int) data_get($opsMovement, 'passengers.official_total', 0);
        $grandTotal =
            (int) data_get($opsMovement, 'passengers.grand_total', 0) ?:
            $adultTotal + $childTotal + $infantTotal + $officialTotal;
        $childWithBed = (int) data_get($opsMovement, 'passengers.child_with_bed_total', 0);
        $childNoBed = (int) data_get($opsMovement, 'passengers.child_no_bed_total', 0);

        $displayTourLeaders = $tourLeaders
            ->filter(function ($row) {
                return is_array($row);
            })
            ->map(function ($row) {
                $type = trim((string) ($row['type'] ?? ''));
                $name = trim((string) ($row['name'] ?? ''));
                $contactNumber = trim((string) ($row['contact_number'] ?? ''));

                return [
                    'type' => $type !== '' ? $type : 'Official',
                    'name' => $name !== '' ? $name : '-',
                    'contact_number' => $contactNumber !== '' ? $contactNumber : '-',
                ];
            })
            ->values();
    @endphp

    {{-- Group Duration --}}
    <div class="group-duration">
        GROUP DURATION &ndash; {{ $opsMovement['departure_return_range'] ?? '-' }}
    </div>

    {{-- PASSENGER DETAILS --}}
    <div class="section-title">Passenger Details</div>
    <table class="section-table">
        <tr>
            <th style="width: 14%;" rowspan="2" class="text-center">No of Mutawif<br>&amp; Official</th>
            <th colspan="4" class="text-center" style="width: 32%;">No of Pax</th>
        </tr>
        <tr>
            <th class="text-center" style="width: 8%;">Adult</th>
            <th class="text-center" style="width: 8%;">Child</th>
            <th class="text-center" style="width: 8%;">Inf</th>
            <th class="text-center" style="width: 8%;">Total</th>
        </tr>
        <tr>
            <td class="text-center">{{ $officialTotal }}</td>
            <td class="text-center">{{ $adultTotal }}</td>
            <td class="text-center">{{ $childTotal }}</td>
            <td class="text-center">{{ $infantTotal }}</td>
            <td class="text-center">{{ $grandTotal }}</td>
        </tr>
    </table>

    <div class="section-title">Tour Leader</div>
    <table class="section-table">
        <tr>
            <th>Official Location</th>
            <th>Official Name</th>
            <th>Contact Number</th>
        </tr>
        @forelse ($displayTourLeaders as $leader)
            <tr>
                <td>{{ $leader['type'] }}</td>
                <td>{{ $leader['name'] }}</td>
                <td>{{ $leader['contact_number'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="3" class="text-center">No tour leader rows available.</td>
            </tr>
        @endforelse
    </table>

    {{-- FLIGHT SCHEDULE --}}
    <div class="section-title">Flight Schedule</div>
    <table class="section-table">
        <tr>
            <th style="width: 18%;">Carrier</th>
            <th style="width: 12%;" class="text-center">From</th>
            <th style="width: 12%;" class="text-center">To</th>
            <th style="width: 14%;" class="text-center">Date</th>
            <th style="width: 8%;" class="text-center">ETD</th>
            <th style="width: 8%;" class="text-center">ETA</th>
            <th style="width: 28%;">Remarks</th>
        </tr>
        @forelse ($flights as $flight)
            @php
                $dep = $flight['departure_datetime'] ?? null;
                $arr = $flight['arrival_datetime'] ?? null;
                try {
                    $depDate = $dep ? \Carbon\Carbon::parse($dep)->format('d M Y') : '-';
                    $depTime = $dep ? \Carbon\Carbon::parse($dep)->format('H:i') : '-';
                    $arrTime = $arr ? \Carbon\Carbon::parse($arr)->format('H:i') : '-';
                } catch (\Exception $e) {
                    $depDate = $dep ?? '-';
                    $depTime = '-';
                    $arrTime = $arr ?? '-';
                }
            @endphp
            <tr>
                <td>{{ $flight['airline'] ?? ($flight['pnr'] ?? '-') }}</td>
                <td class="text-center">{{ $flight['from'] ?? '-' }}</td>
                <td class="text-center">{{ $flight['to'] ?? '-' }}</td>
                <td class="text-center">{{ $depDate }}</td>
                <td class="text-center">{{ $depTime }}</td>
                <td class="text-center">{{ $arrTime }}</td>
                <td>{{ $flight['remarks'] ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center">No flight schedule data.</td>
            </tr>
        @endforelse
    </table>

    {{-- ACCOMMODATION --}}
    <div class="section-title">Accommodation</div>
    <table class="section-table">
        <tr>
            <th style="width: 10%;">City</th>
            <th style="width: 12%;">Hotel Name</th>
            <th style="width: 12%;" class="text-center">Check In</th>
            <th style="width: 12%;" class="text-center">Check Out</th>
            <th style="width: 7%;" class="text-right">Nights</th>
            <th style="width: 7%;" class="text-right">Single</th>
            <th style="width: 7%;" class="text-right">Double</th>
            <th style="width: 7%;" class="text-right">Triple</th>
            <th style="width: 7%;" class="text-right">Quad</th>
            <th style="width: 7%;" class="text-right">Infant</th>
            <th style="width: 12%;">Remarks</th>
        </tr>
        @forelse ($accommodations as $accommodation)
            @php
                $singleRoomCount =
                    (int) data_get($accommodation, 'room_counts.single', 0) +
                    (int) data_get($accommodation, 'room_counts.child_with_bed', 0) +
                    (int) data_get($accommodation, 'room_counts.child_no_bed', 0);
            @endphp
            <tr>
                <td>{{ $accommodation['location'] ?? '-' }}</td>
                <td>{{ $accommodation['hotel_name'] ?? '-' }}</td>
                <td class="text-center">{{ $accommodation['check_in'] ?? '-' }}</td>
                <td class="text-center">{{ $accommodation['check_out'] ?? '-' }}</td>
                <td class="text-right">{{ (int) ($accommodation['nights'] ?? 0) }}</td>
                <td class="text-right">{{ $singleRoomCount }}</td>
                <td class="text-right">{{ (int) data_get($accommodation, 'room_counts.double', 0) }}</td>
                <td class="text-right">{{ (int) data_get($accommodation, 'room_counts.triple', 0) }}</td>
                <td class="text-right">{{ (int) data_get($accommodation, 'room_counts.quad', 0) }}</td>
                <td class="text-right">{{ (int) data_get($accommodation, 'room_counts.infant', 0) }}</td>
                <td>{{ $accommodation['remarks'] ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="11" class="text-center">No accommodation data.</td>
            </tr>
        @endforelse
    </table>

    {{-- RAWDAH TASREEH --}}
    <div class="section-title">Rawdah Tasreeh</div>
    <table class="section-table">
        <tr>
            <th style="width: 15%;">Date</th>
            <th style="width: 11%;" class="text-right">Women Pax</th>
            <th style="width: 12%;" class="text-center">Women Time</th>
            <th style="width: 11%;" class="text-right">Men Pax</th>
            <th style="width: 12%;" class="text-center">Men Time</th>
            <th style="width: 8%;" class="text-right">Total</th>
            <th>Remarks (Blocked dates)</th>
        </tr>
        @forelse ($rawdahRows as $row)
            @php
                $totalPax = (int) ($row['women_passengers'] ?? 0) + (int) ($row['men_passengers'] ?? 0);
            @endphp
            <tr>
                <td>{{ $row['date'] ?? '-' }}</td>
                <td class="text-right">{{ $row['women_passengers'] === 0 ? '-' : $row['women_passengers'] }}</td>
                <td class="text-center">{{ $row['women_time'] ?? '-' }}</td>
                <td class="text-right">{{ $row['men_passengers'] === 0 ? '-' : $row['men_passengers'] }}</td>
                <td class="text-center">{{ $row['men_time'] ?? '-' }}</td>
                <td class="text-right">{{ $totalPax }}</td>
                <td>{{ $row['remarks'] ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center">No rawdah tasreeh data.</td>
            </tr>
        @endforelse
        {{-- <tr>
            <td colspan="7" class="text-center legend-text">
                (Preferred date and time is based on availability)
            </td>
        </tr> --}}
    </table>

    {{-- TRANSPORTATION PLAN --}}
    <div class="section-title">Transportation Plan</div>
    <table class="section-table">
        <tr>
            <th style="width: 5%;" class="text-center">No</th>
            <th style="width: 20%;">From</th>
            <th style="width: 22%;">To</th>
            <th style="width: 14%;" class="text-center">Date</th>
            <th style="width: 10%;" class="text-center">Time</th>
            <th>Remarks</th>
        </tr>
        @forelse ($transportRows as $index => $row)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $row['from'] ?? '-' }}</td>
                <td>{{ $row['to'] ?? '-' }}</td>
                <td class="text-center">{{ $row['travel_date'] ?? '-' }}</td>
                <td class="text-center">{{ $row['travel_time'] ?? '-' }}</td>
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
