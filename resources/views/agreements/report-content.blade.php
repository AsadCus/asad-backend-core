@extends('layout-report')

@section('document-title', 'Agreement for Installment Payment - ' . ($agreement['quotation']['order']['order_number'] ??
    'Agreement'))

@section('extra-company-reg')
    @if ($agreement['sales_registration_number'] ?? false)
        REGISTRATION NO. {{ $agreement['sales_registration_number'] }}&nbsp;&nbsp;
    @endif
@endsection

@section('title-bar')
    Agreement for Installment Payment between Employer &amp; Employment Agency
@endsection

@push('styles')
    <style>
        @@page {
            size: A4;
            margin: 1cm 1.5cm;
        }

        /* Agreement uses 50/50 logo/info split */
        .logo-cell {
            width: 50%;
            vertical-align: top;
        }

        .info-cell {
            width: 50%;
            text-align: right;
            vertical-align: top;
            font-size: 10px;
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
            font-size: 11px;
        }

        .info-section {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: collapse;
        }

        .info-section td {
            padding: 8px;
            border: 1px solid #000;
            vertical-align: top;
        }

        .info-label {
            font-weight: bold;
            background-color: #d3d3d3;
            width: 35%;
            padding: 8px;
        }

        .side-by-side {
            width: 100%;
            margin-bottom: 15px;
        }

        .side-by-side td {
            width: 50%;
            vertical-align: top;
        }

        .agreement-text {
            text-align: justify;
            margin: 15px 0;
            line-height: 1.6;
        }

        .payment-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        .payment-table th,
        .payment-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            text-align: center;
        }

        .payment-table th {
            background-color: #d3d3d3;
            font-weight: bold;
            font-size: 9px;
            padding: 8px;
        }

        .payment-table td {
            font-size: 9px;
            padding: 8px;
        }

        .section-title {
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 8px;
        }

        .consequence-text {
            text-align: justify;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .late-payment {
            margin: 15px 0;
        }

        .signature-section {
            margin-top: 40px;
            width: 100%;
        }

        .signature-section td {
            width: 50%;
            vertical-align: top;
            padding: 0 10px;
        }

        .signature-box {
            min-height: 80px;
            margin-bottom: 5px;
        }

        .signature-line {
            border-bottom: 1px solid #000;
            width: 200px;
            margin-bottom: 3px;
        }

        .signature-label {
            font-weight: bold;
            margin-top: 10px;
        }

        .right-align {
            text-align: right;
        }
    </style>
@endpush

@section('report-content')

    @php
        $invoices = $agreement['placement_fee_invoices'] ?? [];
    @endphp

    <!-- Employer & Order Information -->
    <table class="side-by-side">
        <tr>
            <td>
                <table class="info-section">
                    <tr>
                        <td class="info-label">Name of Employer</td>
                        <td>{{ $agreement['customer_name'] }}</td>
                    </tr>
                </table>
            </td>
            <td>
                <table class="info-section">
                    <tr>
                        <td class="info-label">Order Number</td>
                        <td>{{ data_get($agreement, 'quotation.order.order_number', '-') }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Agreement Date</td>
                        <td>{{ $agreement['agreement_date'] }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Total Placement Fee</td>
                        <td>{{ \App\Helpers\FormatService::formatCurrency($agreement['loan_amount'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Monthly Salary</td>
                        <td>{{ \App\Helpers\FormatService::formatCurrency($agreement['monthly_salary'] ?? 0) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Agreement Text -->
    <div class="agreement-text">
        This installment payment agreement, hereinafter known as the "Agreement," is entered into on the date above, by
        and between the employer (name as above) and Urban Care Employment Agency, 931 Yishun Central 1, #01-109
        Singapore 760931 (collectively referred to as the "Parties").
    </div>

    <div class="agreement-text">
        In consideration of the mutual promises in this agreement, which receipts and sufficiency hereby are
        acknowledge, the Parties further agree to the terms as follows:
    </div>

    <div class="agreement-text">
        The Employment Agency hereby agrees to accept the Employer balance payment of the Total Placement Fee stated
        above of the Migrant Domestic Worker (MDW) stated above.
    </div>

    <!-- Payment Schedule Table -->
    <table class="payment-table">
        <thead>
            <tr>
                <th>No.</th>
                <th>Monthly Placement Fee</th>
                <th>Payment Due Date</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoices as $i => $invoice)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ \App\Helpers\FormatService::formatCurrency($invoice['amount']) }}</td>
                    <td>{{ $invoice['due_date'] }}</td>
                    <td>{{ $invoice['description'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Payment Term Section -->
    <div class="section-title">Payment Term</div>
    <div class="consequence-text">
        This agreement shall commence on Agreement date stated above and continue every twentieth (20th) day of each
        succeeding month until the outstanding balance is paid in full by the Employer. The payment will be paid by
        Paynow (UEN S3496387X) or Bank Transfer to DBS Business Current Account 072-131956-0. The employer can pay more
        than but not less than the agreed monthly installments.
    </div>

    <!-- Consequences Section -->
    <div class="section-title">Consequences</div>
    <div class="consequence-text">
        If the Employer fails to pay on the agreed due date, the Employment Agency shall consider an extension of three
        (3) business days. Failure to do so shall results in the additional late payment interest rate of three percent
        (3%) of the Employer's due amount.
    </div>

    <div class="late-payment">
        <strong>Late payment interest amount:
            {{ \App\Helpers\FormatService::formatCurrency($agreement['late_payment_interest_amount'] ?? 0) }}</strong>
    </div>

    <!-- Signature Section -->
    <table class="signature-section">
        <tr>
            <td>
                <div class="signature-box"></div>
                <div class="signature-line"></div>
                <div class="signature-label">Employer Signature / Name</div>
                <div style="margin-top: 3px; font-size: 9px;">{{ $employerName ?? '' }}</div>
            </td>
            <td class="right-align">
                @if (!empty($branding['show_signature']))
                    <div style="text-align: right; margin-bottom: 4px;">
                        @if (
                            ($is_pdf ?? false) &&
                                !empty($branding['signature_path_absolute']) &&
                                file_exists($branding['signature_path_absolute']))
                            <img src="{{ $branding['signature_path_absolute'] }}" alt="Authorised Signature"
                                style="max-height: 60px; width: auto; display: block;">
                        @elseif(!empty($branding['signature_url']))
                            <img src="{{ $branding['signature_url'] }}" alt="Authorised Signature"
                                style="max-height: 60px; width: auto; display: block;">
                        @endif
                    </div>
                @else
                    <div class="signature-box"></div>
                @endif
                @if (!empty($branding['show_stamp']))
                    <div style="text-align: right; margin-top: 10px; margin-bottom: 4px;">
                        @if (($is_pdf ?? false) && !empty($branding['stamp_path_absolute']) && file_exists($branding['stamp_path_absolute']))
                            <img src="{{ $branding['stamp_path_absolute'] }}" alt="Company Stamp"
                                style="max-height: 60px; width: auto; display: block;">
                        @elseif(!empty($branding['stamp_url']))
                            <img src="{{ $branding['stamp_url'] }}" alt="Company Stamp"
                                style="max-height: 60px; width: auto; display: block;">
                        @endif
                    </div>
                @endif
                <div class="signature-line" style="margin-left: auto;"></div>
                <div class="signature-label">{{ $branding['company_name'] ?? 'Urban Care Employment Agency' }}</div>
                <div class="signature-label">Licence No. 25C2708</div>
            </td>
        </tr>
    </table>

    @if (!empty($branding['footer_text']))
        <div style="margin-top: 20px; font-size: 9px; color: #555; border-top: 1px solid #ddd; padding-top: 8px;">
            {!! nl2br(e($branding['footer_text'])) !!}
        </div>
    @endif

@endsection
