<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    @php
        $invoices = $agreement['placement_fee_invoices'] ?? [];
    @endphp
    <title>Agreement for Installment Payment - {{ $agreement['quotation']['order']['order_number'] ?? 'Agreement' }}
    </title>
    <style>
        @page {
            size: A4;
            margin: 1cm 1.5cm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #000;
        }

        .header-table {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: collapse;
        }

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
            max-width: 280px;
            max-height: 120px;
            height: auto;
            width: auto;
            display: block;
        }

        .info-cell b {
            font-size: 11px;
        }

        .title-bar {
            background-color: #40A09D;
            color: white;
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            padding: 10px;
            margin-bottom: 15px;
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

        .payment-table .amount-col {
            text-align: center;
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
</head>

<body>
    <!-- Header Section -->
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                <img src="{{ public_path('logo_agency.png') }}">
            </td>
            <td class="info-cell">
                <div>
                    <b style="margin-bottom: 4px; font-size: 13px; color: #333;">Urban Care Employment Agency</b><br>
                    931 Yishun Central 1<br>
                    #01-109, Singapore 760931<br>
                    <div style="margin-top: 4px;">
                        <!-- <b>REGISTRATION NO. R25128539</b><br> -->
                        <b>LICENSE NO. 25C2708</b>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Title Bar -->
    <div class="title-bar">
        Agreement for Installment Payment between Employer & Employment Agency
    </div>

    <!-- Employer & Order Information -->
    <table class="side-by-side">
        <tr>
            <td>

                <table class="info-section">
                    <tr>
                        <td class="info-label">Name of Employer</td>
                        <td>{{ $agreement['customer_name'] }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Name of MDW</td>
                        <td>{{ $agreement['maid_name'] }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Passport No.</td>
                        <td>{{ $agreement['maid_passport'] }}</td>
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
                        <td>${{ number_format($agreement['loan_amount'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Monthly Salary</td>
                        <td>${{ number_format($agreement['monthly_salary'] ?? 0, 2) }}</td>
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
                    <td>${{ number_format($invoice['amount'], 2) }}</td>
                    <td>
                        {{ $invoice['due_date'] }}
                    </td>
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
            ${{ number_format($agreement['late_payment_interest_amount'] ?? 0, 2) }}</strong>
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
                <div class="signature-box"></div>
                <div class="signature-line" style="margin-left: auto;"></div>
                <div class="signature-label">Urban Care Employment Agency</div>
                <div class="signature-label">Licence No. 25C2708</div>
            </td>
        </tr>
    </table>

</body>

</html>
