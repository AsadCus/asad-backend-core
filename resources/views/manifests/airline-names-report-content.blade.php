@extends('layout-report')

@section('document-title', 'Manifest Airline Names - ' . ($manifest['manifest_number'] ?? 'Manifest'))

@section('title-bar')
    Manifest Airline Name List
@endsection

@section('body-class', 'is-landscape')

@push('styles')
    <style>
        @page {
            size: A4 landscape;
            margin: 0.1cm 0.2cm;
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

        .airline-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            table-layout: fixed;
        }

        .airline-table th,
        .airline-table td {
            border: 1px solid #d7dde3;
            padding: 5px 6px;
            font-size: 8.5px;
            vertical-align: middle;
            text-align: left;
            word-break: break-word;
        }

        .airline-table th {
            background-color: #f4f8fb;
            font-weight: 700;
        }

        .airline-table .col-sn {
            width: 4%;
            text-align: center;
        }

        .airline-table .col-name {
            width: 17%;
        }

        .airline-table .col-passport {
            width: 9%;
        }

        .airline-table .col-gender {
            width: 6%;
        }

        .airline-table .col-nationality {
            width: 8%;
        }

        .airline-table .col-dob,
        .airline-table .col-issue,
        .airline-table .col-expiry {
            width: 8%;
        }

        .airline-table .col-place {
            width: 10%;
        }

        .airline-table .col-remarks {
            width: 14%;
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
            <td class="summary-label">Manifest Number</td>
            <td>{{ $manifest['manifest_number'] ?? '-' }}</td>
            <td class="summary-label">Package</td>
            <td>{{ $manifest['package_name'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="summary-label">Departure Date</td>
            <td>{{ $manifest['departure_date'] ?? '-' }}</td>
            <td class="summary-label">Return Date</td>
            <td>{{ $manifest['return_date'] ?? '-' }}</td>
        </tr>
    </table>

    <table class="airline-table">
        <thead>
            <tr>
                <th class="col-sn">No</th>
                <th class="col-name">Name as per Passport</th>
                <th class="col-passport">Passport No</th>
                <th class="col-gender">Gender</th>
                <th class="col-nationality">Nationality</th>
                <th class="col-dob">Date of Birth</th>
                <th class="col-issue">Date of Issue</th>
                <th class="col-expiry">Date of Expiry</th>
                <th class="col-place">Issue Place</th>
                <th class="col-remarks">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($members as $index => $member)
                <tr>
                    <td class="col-sn">{{ $index + 1 }}</td>
                    <td class="col-name">{{ $member['name_as_per_passport'] ?? '-' }}</td>
                    <td class="col-passport">{{ $member['passport_number'] ?? '-' }}</td>
                    <td class="col-gender">{{ $member['gender'] ?? '-' }}</td>
                    <td class="col-nationality">{{ $member['nationality'] ?? '-' }}</td>
                    <td class="col-dob">{{ $member['date_of_birth'] ?? '-' }}</td>
                    <td class="col-issue">{{ $member['date_of_issue'] ?? '-' }}</td>
                    <td class="col-expiry">{{ $member['date_of_expiry'] ?? '-' }}</td>
                    <td class="col-place">{{ $member['issue_place'] ?? '-' }}</td>
                    <td class="col-remarks">{{ $member['remarks'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">No manifest members found.</td>
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
