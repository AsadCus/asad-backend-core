@extends('layout-report')

@section('document-title', 'Package - ' . ($data['package_number'] ?? 'PACKAGE'))

@section('title-bar')
    PACKAGE
@endsection

@push('styles')
    @php
        $sectionSpacingPreset = $branding['section_spacing_preset'] ?? 'normal';
        $moduleSpacing = [
            'compact' => ['block' => '8px', 'table_top' => '6px', 'title_top' => '10px', 'title_bottom' => '6px'],
            'normal' => ['block' => '10px', 'table_top' => '8px', 'title_top' => '14px', 'title_bottom' => '6px'],
            'relaxed' => ['block' => '16px', 'table_top' => '12px', 'title_top' => '20px', 'title_bottom' => '10px'],
        ][$sectionSpacingPreset] ?? [
            'block' => '10px',
            'table_top' => '8px',
            'title_top' => '14px',
            'title_bottom' => '6px',
        ];
    @endphp
    <style>
        /* ── Content Wrapper ── */
        .content-wrapper {
            padding: 0;
        }

        /* ── Meta Info Table ── */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: {{ $moduleSpacing['block'] }};
        }

        .meta-table td {
            border: 1px solid #d9d9d9;
            padding: 5px 8px;
            vertical-align: top;
            font-size: 11px;
        }

        .meta-label {
            width: 20%;
            font-weight: bold;
            background: #f6f7f8;
            white-space: nowrap;
            color: #333;
        }

        /* ── Section Title ── */
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

        /* ── Section Detail Table ── */
        .section-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: {{ $moduleSpacing['block'] }};
        }

        .section-table th,
        .section-table td {
            border: 1px solid #d9d9d9;
            padding: 4px 8px;
            text-align: left;
            vertical-align: top;
            font-size: 11px;
        }

        .section-table thead th {
            background: #f6f7f8;
            font-weight: bold;
            color: #333;
        }

        .section-table tbody tr:nth-child(even) td {
            background: #fafafa;
        }

        .footer-note {
            text-align: right;
        }

        /* ── Empty state ── */
        .muted {
            color: #888;
            font-style: italic;
            font-size: 10px;
            padding: 4px 0 {{ $moduleSpacing['table_top'] }};
        }
    </style>
@endpush

@section('report-content')
    <div class="content-wrapper">
        <div class="section-title">Package Information</div>
        <table class="meta-table">
            <tr>
                <td class="meta-label">Package No.</td>
                <td>{{ $data['package_number'] ?? '-' }}</td>
                <td class="meta-label">Package Name</td>
                <td>{{ $data['name'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="meta-label">Departure Date</td>
                <td>{{ $data['departure_date'] ?? '-' }}</td>
                <td class="meta-label">Return Date</td>
                <td>{{ $data['return_date'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="meta-label">Seats</td>
                <td>{{ $data['total_seats'] ?? '-' }}</td>
                <td class="meta-label">Seats Left</td>
                <td>{{ $data['seats_left'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="meta-label">Status</td>
                <td colspan="3">{{ ucfirst($data['status'] ?? '-') }}</td>
            </tr>
        </table>

        <div class="section-title">Pricing</div>
        <table class="meta-table">
            <tr>
                <td class="meta-label">Single Sharing</td>
                <td>{{ $data['price_single'] ?? '-' }}</td>
                <td class="meta-label">Double Sharing</td>
                <td>{{ $data['price_double'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="meta-label">Triple Sharing</td>
                <td>{{ $data['price_triple'] ?? '-' }}</td>
                <td class="meta-label">Quad Sharing</td>
                <td>{{ $data['price_quad'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="meta-label">Child w/ Bed</td>
                <td>{{ $data['child_with_bed_price'] ?? '-' }}</td>
                <td class="meta-label">Child w/o Bed</td>
                <td>{{ $data['child_no_bed_price'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="meta-label">Infant</td>
                <td colspan="3">{{ $data['infant_price'] ?? '-' }}</td>
            </tr>
        </table>

        <div class="section-title">Flight Details</div>
        @if (!empty($data['flights']))
            <table class="section-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Route</th>
                        <th>Description</th>
                        <th>Airline</th>
                        <th>PNR</th>
                        <th>Departure</th>
                        <th>Arrival</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['flights'] as $index => $flight)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ ($flight['from'] ?? '-') . '->' . ($flight['to'] ?? '-') }}</td>
                            <td>{{ $flight['description'] ?? '-' }}</td>
                            <td>{{ $flight['airline'] ?? '-' }}</td>
                            <td>{{ $flight['pnr'] ?? '-' }}</td>
                            <td>{{ $flight['departure_datetime'] ?? '-' }}</td>
                            <td>{{ $flight['arrival_datetime'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="muted">No flights added.</div>
        @endif

        <div class="section-title">Transportation Plan</div>
        @if (!empty($data['transportation_plans']))
            <table class="section-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['transportation_plans'] as $index => $plan)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $plan['from'] ?? '-' }}</td>
                            <td>{{ $plan['to'] ?? '-' }}</td>
                            <td>{{ $plan['travel_date'] ?? '-' }}</td>
                            <td>{{ $plan['travel_time'] ?? '-' }}</td>
                            <td>{{ $plan['remarks'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="muted">No transportation plans added.</div>
        @endif

        <div class="section-title">Visa</div>
        <table class="meta-table">
            <tr>
                <td class="meta-label">Visa Type</td>
                <td>{{ $data['visa_type'] ?? '-' }}</td>
            </tr>
        </table>

        <div class="section-title">Vehicle</div>
        <table class="meta-table">
            <tr>
                <td class="meta-label">Vehicle Type</td>
                <td>{{ $data['vehicle_type'] ?? '-' }}</td>
                <td class="meta-label">Driver</td>
                <td>{{ $data['vehicle_driver_name'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="meta-label">Driver Contact</td>
                <td colspan="3">{{ $data['vehicle_driver_contact_number'] ?? '-' }}</td>
            </tr>
        </table>

        <div class="section-title">Train Ticket Details</div>
        <table class="meta-table">
            <tr>
                <td class="meta-label">Train Ticket Type</td>
                <td>{{ $data['ticket_type'] ? str_replace('_', ' ', ucfirst($data['ticket_type'])) : '-' }}</td>
            </tr>
            <tr>
                <td class="meta-label">Train Description</td>
                <td>{{ $data['train_description'] ?? '-' }}</td>
            </tr>
        </table>
        @if (!empty($data['train_tickets']))
            <table class="section-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['train_tickets'] as $index => $ticket)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $ticket['from'] ?? '-' }}</td>
                            <td>{{ $ticket['to'] ?? '-' }}</td>
                            <td>{{ $ticket['travel_date'] ?? '-' }}</td>
                            <td>{{ $ticket['travel_time'] ?? '-' }}</td>
                            <td>{{ $ticket['remarks'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="muted">No train tickets added.</div>
        @endif

        <div class="section-title">Accommodations</div>
        @if (!empty($data['accommodations']))
            <table class="section-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Location</th>
                        <th>Hotel</th>
                        <th>Meal Plan</th>
                        <th>IC</th>
                        <th>IC Contact</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['accommodations'] as $index => $accommodation)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $accommodation['location'] ?? '-' }}</td>
                            <td>{{ $accommodation['hotel_name'] ?? '-' }}</td>
                            <td>{{ $accommodation['type_of_meal'] ?? '-' }}</td>
                            <td>{{ $accommodation['ic'] ?? '-' }}</td>
                            <td>{{ $accommodation['ic_contact_number'] ?? '-' }}</td>
                            <td>{{ $accommodation['check_in'] ?? '-' }}</td>
                            <td>{{ $accommodation['check_out'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="muted">No accommodations added.</div>
        @endif

        <div class="section-title">Rawdah Tasreeh</div>
        @if (!empty($data['rawdah_tasreehs']))
            <table class="section-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Women Pax</th>
                        <th>Women Time</th>
                        <th>Men Pax</th>
                        <th>Men Time</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['rawdah_tasreehs'] as $index => $tasreeh)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $tasreeh['date'] ?? '-' }}</td>
                            <td>{{ $tasreeh['women_passengers'] === 0 ? '-' : $tasreeh['women_passengers'] }}</td>
                            <td>{{ $tasreeh['women_time'] ?? '-' }}</td>
                            <td>{{ $tasreeh['men_passengers'] === 0 ? '-' : $tasreeh['men_passengers'] }}</td>
                            <td>{{ $tasreeh['men_time'] ?? '-' }}</td>
                            <td>{{ $tasreeh['remarks'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="muted">No rawdah tasreeh records added.</div>
        @endif

        <div class="section-title">Officials</div>
        @if (!empty($data['officials']))
            <table class="section-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Nationality</th>
                        <th>Passport Number</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['officials'] as $index => $official)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ ucfirst($official['type'] ?? '-') }}</td>
                            <td>{{ $official['name'] ?? '-' }}</td>
                            <td>{{ $official['contact_number'] ?? '-' }}</td>
                            <td>{{ $official['nationality'] ?? '-' }}</td>
                            <td>{{ $official['passport_number'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="muted">No officials added.</div>
        @endif

        <div class="section-title">Package Inclusions</div>
        <table class="meta-table">
            <tr>
                <td class="meta-label">Included</td>
                <td>{{ $data['included'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="meta-label">Not Included</td>
                <td>{{ $data['not_included'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="meta-label">Offer</td>
                <td>{{ $data['offer'] ?? '-' }}</td>
            </tr>
        </table>

        <div class="section-title">Remarks</div>
        <table class="meta-table">
            <tr>
                <td class="meta-label">Remarks</td>
                <td>{{ $data['remarks'] ?? '-' }}</td>
            </tr>
        </table>

        <div class="footer-section">
            @php
                $activeNotes = collect($data['notes'] ?? [])
                    ->filter(fn($n) => !empty(trim(strip_tags($n['description'] ?? ''))))
                    ->sortBy('sort_order')
                    ->values();
            @endphp
            @include('partials.report-notes')

            @if (!empty($branding['footer_text']))
                <div class="footer-note">{!! nl2br(e($branding['footer_text'])) !!}</div>
            @endif

            @include('partials.report-signature-stamp')
        </div>
    </div>
@endsection
