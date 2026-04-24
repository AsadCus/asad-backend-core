@extends('layout-report')

@section('document-title', 'Manifest Check-In Room List - ' . ($manifest['room_check_location_label'] ?? '-'))

@section('title-bar')
    Manifest Check-In Room List - {{ $manifest['room_check_location_label'] ?? '-' }}
@endsection

@section('body-class', 'is-landscape')

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
            size: A4 landscape;
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

        .summary-head {
            text-align: center;
        }

        .room-check-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
            margin-top: {{ $moduleSpacing['table_top'] }};
        }

        .room-check-table th,
        .room-check-table td {
            border: 1px solid #d7dde3;
            padding: 6px 4px;
            font-size: 9px;
            text-align: center;
            vertical-align: middle;
        }

        .room-check-table th {
            background-color: #f4f8fb;
            font-weight: 700;
            line-height: 1.25;
            white-space: nowrap;
        }

        .room-check-table th.member-name,
        .room-check-table td.member-name,
        .room-check-table th.member-remarks,
        .room-check-table td.member-remarks,
        .room-check-table th.room-remarks,
        .room-check-table td.room-remarks {
            text-align: left;
            padding-left: 8px;
        }

        .group-bg-a {
            background-color: #fff7ed;
        }

        .group-bg-b {
            background-color: #ffedd5;
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

        .beds-check {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            justify-content: center;
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
        $rows = collect($manifest['room_check_rows'] ?? [])
            ->filter(function ($row) {
                return ($row['status'] ?? null) !== 'cancelled';
            })
            ->values();

        $accommodations = collect($manifest['package_accommodations'] ?? [])->values();

        $groupedRows = [];
        foreach ($rows as $index => $row) {
            $groupKey = $row['sharing_group_key'] ?? 'group-' . $index;
            if (!isset($groupedRows[$groupKey])) {
                $groupedRows[$groupKey] = [];
            }
            $groupedRows[$groupKey][] = $row;
        }

        $roomColorMap = [];
        $currentColor = 'group-bg-a';
        $lastRoomLabel = null;

        foreach ($groupedRows as $groupKey => $groupRows) {
            $firstRoomLabel = trim((string) ($groupRows[0]['room_label'] ?? ''));
            $normalizedLabel = $firstRoomLabel !== '' ? strtolower($firstRoomLabel) : '__group__' . $groupKey;

            if ($lastRoomLabel !== null && $lastRoomLabel !== $normalizedLabel) {
                $currentColor = $currentColor === 'group-bg-a' ? 'group-bg-b' : 'group-bg-a';
            }

            $roomColorMap[$groupKey] = $currentColor;
            $lastRoomLabel = $normalizedLabel;
        }

        $roomIndex = 1;
        $rowNumber = 1;
        $roomTypeLabels = [
            'single' => 'Single',
            'twin' => 'Twin',
            'double' => 'Double',
            'triple' => 'Triple',
            'quad' => 'Quad',
        ];
        $bedTypeLabels = [
            'single' => 'Single',
            'king' => 'King',
            'queen' => 'Queen',
        ];
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

    <table class="room-check-table">
        <thead>
            <tr>
                <th class="sn-col" rowspan="2">S/N</th>
                <th class="member-name" rowspan="2">Name as per passport</th>
                <th rowspan="2">Relationship</th>
                <th rowspan="2">Passport No</th>
                <th rowspan="2">Room Label</th>
                <th rowspan="2">Room No</th>
                <th rowspan="2">Room Type</th>
                <th rowspan="2">Bed Type</th>
                <th rowspan="2">Date of Birth</th>
                <th rowspan="2">Age</th>
                <th rowspan="2">Contact Number</th>
                <th colspan="2">No. Of Beds Checked</th>
                <th rowspan="2">Meal</th>
                <th colspan="2">Remarks</th>
            </tr>
            <tr>
                <th>No. Beds</th>
                <th>Checked</th>
                <th>Member</th>
                <th>Room</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($groupedRows as $groupKey => $groupRows)
                @php
                    $rowSpan = count($groupRows);
                    $first = $groupRows[0];
                    $groupColorClass = $roomColorMap[$groupKey] ?? 'group-bg-a';
                    $bedsCount = (int) ($first['no_of_beds_checked'] ?? 0);
                    $roomTypeValue = strtolower(trim((string) ($first['room_type'] ?? '')));
                    $bedTypeValue = strtolower(trim((string) ($first['bed_type'] ?? '')));
                    $roomTypeDisplay =
                        $roomTypeValue !== '' ? $roomTypeLabels[$roomTypeValue] ?? ucfirst($roomTypeValue) : '-';
                    $bedTypeDisplay =
                        $bedTypeValue !== '' ? $bedTypeLabels[$bedTypeValue] ?? ucfirst($bedTypeValue) : '-';
                    $extraBedCount = collect($groupRows)
                        ->filter(function ($memberRow) {
                            return strtolower(trim((string) ($memberRow['sharing_plan'] ?? ''))) === 'child_with_bed';
                        })
                        ->count();

                    if ($bedsCount < 1) {
                        $roomType = $roomTypeValue;
                        $bedType = $bedTypeValue;

                        if ($roomType === 'single') {
                            $bedsCount = 1;
                        } elseif ($roomType === 'twin' && $bedType === 'single') {
                            $bedsCount = 2;
                        } elseif ($roomType === 'triple' && $bedType === 'single') {
                            $bedsCount = 3;
                        } elseif ($roomType === 'quad' && $bedType === 'single') {
                            $bedsCount = 4;
                        } elseif ($roomType === 'double' && $bedType === 'single') {
                            $bedsCount = 2;
                        } else {
                            $bedsCount = 1;
                        }
                    }

                    $bedsCount += $extraBedCount;
                @endphp

                @foreach ($groupRows as $memberIndex => $row)
                    @php
                        $memberRemarks = trim((string) ($row['remarks'] ?? ''));
                        $memberSharingPlan = strtolower(trim((string) ($row['sharing_plan'] ?? '')));

                        if ($memberSharingPlan === 'child_with_bed') {
                            if ($memberRemarks === '') {
                                $memberRemarks = 'Extra bed';
                            } elseif (!preg_match('/\bextra\s*bed\b/i', $memberRemarks)) {
                                $memberRemarks .= '; Extra bed';
                            }
                        }

                        $roomRemarks = trim((string) ($first['room_remarks'] ?? ''));
                    @endphp

                    <tr class="{{ $groupColorClass }}">
                        <td class="sn-col">{{ $rowNumber }}</td>
                        <td class="member-name">{{ $row['name_as_per_passport'] ?? '-' }}</td>

                        @if ($memberIndex === 0)
                            <td rowspan="{{ $rowSpan }}">{{ $first['room_relationship'] ?? '-' }}</td>
                        @endif

                        <td>{{ $row['passport_number'] ?? '-' }}</td>

                        @if ($memberIndex === 0)
                            <td rowspan="{{ $rowSpan }}">{{ $first['room_label'] ?? 'Room ' . $roomIndex }}</td>
                            <td rowspan="{{ $rowSpan }}">{{ $first['room_number'] ?? '-' }}</td>
                            <td rowspan="{{ $rowSpan }}">{{ $roomTypeDisplay }}</td>
                            <td rowspan="{{ $rowSpan }}">{{ $bedTypeDisplay }}</td>
                        @endif

                        <td>{{ $row['date_of_birth'] ?? '-' }}</td>
                        <td>{{ $row['age'] ?? '-' }}</td>
                        <td style="text-align: left; padding-left: 8px;">{{ $row['contact_no'] ?? '-' }}</td>


                        @if ($memberIndex === 0)
                            <td rowspan="{{ $rowSpan }}">{{ $bedsCount }}</td>
                            <td rowspan="{{ $rowSpan }}">
                                <span
                                    class="print-checkbox {{ !empty($first['number_of_beds_checked']) ? 'checked' : '' }}">
                                    {{ !empty($first['number_of_beds_checked']) ? 'X' : '' }}
                                </span>
                            </td>
                            <td rowspan="{{ $rowSpan }}">{{ $first['meal'] ?? '-' }}</td>
                        @endif

                        <td class="member-remarks">{{ $memberRemarks }}</td>

                        @if ($memberIndex === 0)
                            <td rowspan="{{ $rowSpan }}" class="room-remarks">{{ $roomRemarks }}</td>
                        @endif
                    </tr>

                    @php
                        $rowNumber++;
                    @endphp
                @endforeach

                @php
                    $roomIndex++;
                @endphp
            @empty
                <tr>
                    <td colspan="15">No room members found for this location.</td>
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
