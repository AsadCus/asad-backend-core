@extends('layout-report')

@section('document-title', 'Sales Profile - ' . ($data['name'] ?? 'Sales'))

@section('title-bar')
    SALES PROFILE
@endsection

@push('styles')
    <style>
        @@page {
            size: A4;
            margin: 1.2cm 1.5cm;
        }

        /* Sales uses 45/55 logo/info split */
        .logo-cell {
            width: 45%;
            vertical-align: top;
        }

        .info-cell {
            width: 55%;
            text-align: right;
            vertical-align: top;
            font-size: 11px;
            line-height: 1.5;
        }

        .logo-cell img {
            width: 240px;
            max-width: 240px;
            max-height: 90px;
            height: auto;
            object-fit: contain;
            display: block;
            margin: 0;
        }

        .info-cell b {
            font-size: 12px;
        }

        body {
            font-size: 12px;
        }

        /* Title Bar override for Sales */
        .title-bar {
            font-size: 15px;
            padding: 6px;
            letter-spacing: 3px;
            margin-bottom: 20px;
        }

        /* Content */
        .content-wrapper {
            padding: 0;
        }

        /* Info Table */
        .info-section {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .info-section td {
            padding: 8px 10px;
            border: 1px solid #ddd;
            vertical-align: top;
        }

        .info-section .label-cell {
            width: 35%;
            font-weight: bold;
            background-color: #f5f5f5;
            white-space: nowrap;
        }

        /* Footer */
        .footer-section {
            clear: both;
            padding: 15px 0 0;
            font-size: 11px;
            border-top: none;
        }

        .footer-note {
            text-align: right;
            margin-bottom: 10px;
            line-height: 1.4;
        }
    </style>
@endpush

@section('report-content')

    <!-- Salesperson Details -->
    <div class="content-wrapper">
        <table class="info-section">
            <tr>
                <td class="label-cell">Name</td>
                <td>{{ $data['name'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label-cell">Email</td>
                <td>{{ $data['email'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label-cell">Contact</td>
                <td>{{ $data['contact'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label-cell">Branch</td>
                <td>{{ $data['branch_name'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label-cell">Registration Number</td>
                <td>{{ $data['registration_number'] ?? '-' }}</td>
            </tr>
        </table>

        <!-- Footer -->
        <div class="footer-section">
            @if (!empty($data['notes']) && count($data['notes']) > 0)
                @foreach ($data['notes'] as $note)
                    <div class="footer-note">{!! $note['description'] ?? '' !!}</div>
                @endforeach
            @elseif (!empty($branding['footer_text']))
                <div class="footer-note">{!! nl2br(e($branding['footer_text'])) !!}</div>
            @endif

            @include('partials.report-signature-stamp')
        </div>
    </div>

@endsection
