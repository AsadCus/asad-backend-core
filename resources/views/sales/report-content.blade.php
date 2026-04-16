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

        .payment-title {
            margin: 16px 0 8px;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .payment-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        .payment-table th,
        .payment-table td {
            border: 1px solid #ddd;
            padding: 8px 10px;
            text-align: left;
            vertical-align: middle;
        }

        .payment-table th {
            background-color: #f5f5f5;
            font-weight: bold;
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

        <div class="payment-title">Payment Info</div>
        @if (empty($data['payment_info']) || count($data['payment_info']) === 0)
            <table class="payment-table">
                <tbody>
                    <tr>
                        <td colspan="3">No Payment Records</td>
                    </tr>
                </tbody>
            </table>
        @else
            <table class="payment-table">
                <thead>
                    <tr>
                        <th>Installment</th>
                        <th>Paid / Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (($data['payment_info'] ?? []) as $paymentRow)
                        <tr>
                            <td>{{ $paymentRow['label'] ?? '-' }}</td>
                            <td>{{ number_format((float) ($paymentRow['amount_paid'] ?? 0), 2, '.', ',') }} /
                                {{ number_format((float) ($paymentRow['total_amount'] ?? 0), 2, '.', ',') }}</td>
                            <td>{{ $paymentRow['status'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <!-- Footer -->
        <div class="footer-section">
            @php
                $activeNotes = collect($data['notes'] ?? [])
                    ->filter(fn ($n) => !empty(trim(strip_tags($n['description'] ?? ''))))
                    ->sortBy('sort_order')
                    ->values();
            @endphp

            {{-- Notes: always shown above footer if description is filled --}}
            @include('partials.report-notes')

            {{-- Module footer text from Report Template Settings --}}
            @if (! empty($branding['footer_text']))
                <div class="footer-note">{!! nl2br(e($branding['footer_text'])) !!}</div>

            @include('partials.report-signature-stamp')
        </div>

    </div>

@endsection
