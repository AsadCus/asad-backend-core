<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>{{ $summary['period_label'] ?? 'Payment' }} Payment Summary</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 12px;
        }

        h1 {
            margin: 0;
            font-size: 20px;
        }

        .meta {
            margin-top: 4px;
            color: #4b5563;
            font-size: 11px;
        }

        .totals {
            margin-top: 16px;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #f9fafb;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 8px;
            text-align: left;
        }

        th {
            background: #f3f4f6;
            font-weight: bold;
        }

        .right {
            text-align: right;
        }

        .section-title {
            margin-top: 18px;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <h1>{{ $summary['period_label'] ?? 'Payment' }} Payment Summary</h1>
    <div class="meta">Date Range: {{ $summary['date_range_label'] ?? '-' }}</div>
    <div class="meta">Generated At: {{ now()->translatedFormat('d F Y H:i') }}</div>

    <div class="totals">
        <div class="totals-row">
            <span>Total Receipts</span>
            <strong>{{ $summary['receipt_count'] ?? 0 }}</strong>
        </div>
        <div class="totals-row" style="margin-bottom: 0;">
            <span>Total Payment</span>
            <strong>${{ number_format((float) ($summary['total_amount'] ?? 0), 2) }}</strong>
        </div>
    </div>

    <div class="section-title">Category Breakdown</div>
    <table>
        <thead>
            <tr>
                <th style="width: 55%;">Category</th>
                <th style="width: 20%;" class="right">Receipt Rows</th>
                <th style="width: 25%;" class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($summary['categories'] ?? []) as $row)
                <tr>
                    <td>{{ $row['category'] ?? 'Others' }}</td>
                    <td class="right">{{ $row['receipt_count'] ?? 0 }}</td>
                    <td class="right">${{ number_format((float) ($row['amount'] ?? 0), 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="text-align: center;">No payment data found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Period Buckets</div>
    <table>
        <thead>
            <tr>
                <th style="width: 70%;">Period</th>
                <th style="width: 30%;" class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($summary['buckets'] ?? []) as $bucket)
                <tr>
                    <td>{{ $bucket['label'] ?? '-' }}</td>
                    <td class="right">${{ number_format((float) ($bucket['amount'] ?? 0), 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" style="text-align: center;">No period data found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>

</html>
