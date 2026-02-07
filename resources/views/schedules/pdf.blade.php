<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    @php
        $q = $schedule['quotation'] ?? [];
        $order = $q['order'] ?? [];
        $customer = $q['customer']['user'] ?? [];
        $maid = $q['maid'] ?? [];
        $breakdown = $schedule['breakdown'] ?? [];
    @endphp
    <title>Schedule of Salary Payment - {{ $order['order_number'] ?? 'Schedule' }}</title>
    <style>
        @page {
            size: A4;
            margin: 1cm 1.5cm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            line-height: 1.3;
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
            font-size: 9px;
            line-height: 1.4;
        }

        .logo-cell img {
            max-width: 280px;
            max-height: 100px;
            height: auto;
            width: auto;
            display: block;
        }

        .info-cell b {
            font-size: 10px;
        }

        .title-bar {
            background-color: #40A09D;
            color: white;
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            padding: 4px;
            margin-bottom: 4px;
            letter-spacing: 1px;
        }

        .employer-info {
            width: 100%;
            border-collapse: collapse;
        }

        .employer-info td {
            border: 1px solid #000;
            padding: 4px 6px;
            vertical-align: middle;
            font-size: 8px;
        }

        .employer-label {
            font-weight: bold;
            background-color: #f3f4f6;
            width: 40%;
        }

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
        }

        .schedule-table th,
        .schedule-table td {
            border: 1px solid #000;
            text-align: center;
            font-size: 8px;
        }

        .schedule-table th {
            background-color: #f3f4f6;
            font-weight: bold;
            padding: 4px;
        }

        .schedule-table td {
            padding: 8px 4px;
            vertical-align: middle;
        }

        .schedule-table .signature-col {
            width: 100px;
        }

        .text-right {
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
                        @if ($schedule['sales_registration_number'] ?? false)
                            <b>REGISTRATION NO. {{ $schedule['sales_registration_number'] }}</b><br>
                        @endif
                        <b>LICENCE NO. 25C2708</b>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Title Bar -->
    <div class="title-bar">
        SCHEDULE OF SALARY PAYMENT AND LOAN REPAYMENT
    </div>

    <!-- Employer Information Section -->
    <div style="margin-bottom: 5px;">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 50%; vertical-align: top; padding-right: 5px;">
                    <table class="employer-info" style="width: 100%;">
                        <tr>
                            <td class="employer-label">Name of Employer</td>
                            <td>{{ $customer['name'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="employer-label">Name of MDW</td>
                            <td>{{ $maid['name'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="employer-label">Commencement Date</td>
                            <td>{{ $order['handover_date'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="employer-label">Rest Day of the week</td>
                            <td>{{ $schedule['rest_day_of_the_week'] ?? '-' }}</td>
                        </tr>
                    </table>
                </td>
                <td style="width: 50%; vertical-align: top; padding-left: 5px;">
                    <table class="employer-info" style="width: 100%;">
                        <tr>
                            <td class="employer-label">Order Number</td>
                            <td>{{ $order['order_number'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="employer-label">Passport No</td>
                            <td>{{ $maid['passport_number'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="employer-label">Total Placement Fee</td>
                            <td>{{ \App\Helpers\FormatService::formatCurrency($schedule['loan_amount'] ?? 0) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <div style="margin-bottom: 5px;">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 50%; vertical-align: top; padding-right: 5px;">
                    <table class="employer-info" style="width: 100%;">
                        <tr>
                            <td class="employer-label">Monthly Salary</td>
                            <td>{{ \App\Helpers\FormatService::formatCurrency($schedule['monthly_salary'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <td class="employer-label">Compensation Off in Lieu</td>
                            <td>{{ \App\Helpers\FormatService::formatCurrency($schedule['compensation_off_in_lieu'] ?? 0) }}
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 50%; vertical-align: top; padding-left: 5px;">
                    <table class="employer-info" style="width: 100%;">
                        <tr>
                            <td class="employer-label">Loan Duration (Mth)</td>
                            <td>{{ $schedule['loan_duration_months'] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td class="employer-label">No. of rest day per month</td>
                            <td>{{ $schedule['rest_days_per_month'] ?? '' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <table class="schedule-table">
        <thead>
            <tr>
                <th rowspan="2">No.</th>
                <th>Day</th>
                <th>Mth/Year</th>
                <th>Basic Salary</th>
                <th>Off Day<br>Compensation</th>
                <th>Monthly Loan<br>Repayment</th>
                <th>Total amount<br>received by MDW</th>
                <th rowspan="2">Employer<br>Signature</th>
                <th rowspan="2">MDW<br>Signature</th>
            </tr>
            <tr>
                <th>Hari</th>
                <th>Bln/Tahun</th>
                <th>Gaji</th>
                <th>Gaji Libur</th>
                <th>Hutang</th>
                <th>Uang Saku/Gaji</th>
            </tr>
        </thead>
        <tbody>
            @foreach (array_slice($breakdown, 0, 24) as $i => $row)
                <tr>
                    <td>{{ $row['month'] }}</td>
                    <td>{{ $row['day'] ?? '-' }}</td>
                    <td>{{ $row['month_name'] ?? '-' }}</td>
                    <td>{{ \App\Helpers\FormatService::formatCurrency($row['salary'] ?? 0) }}</td>
                    <td>{{ \App\Helpers\FormatService::formatCurrency($row['compensation_off'] ?? 0) }}</td>
                    <td>{{ \App\Helpers\FormatService::formatCurrency($row['loan_payment'] ?? 0) }}</td>
                    <td>{{ \App\Helpers\FormatService::formatCurrency($row['total_payment'] ?? 0) }}</td>
                    <td class="signature-col"></td>
                    <td class="signature-col"></td>
                </tr>
            @endforeach
        </tbody>
    </table>

</body>

</html>
