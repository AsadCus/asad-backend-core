@extends('layout-report')

@section('document-title', 'Ops Movement Budget - ' . ($opsMovement['package_number'] ?? 'Ops Movement'))

@section('title-bar')
    OPS MOVEMENT - BUDGET
@endsection

@push('styles')
    <style>
        @page {
            size: A4 portrait;
            margin: 0.2cm 0.35cm;
        }

        .summary-grid,
        .section-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 8px;
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
            margin: 8px 0 4px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
            color: #22313f;
            text-transform: uppercase;
        }

        .text-center {
            text-align: center !important;
        }

        .text-right {
            text-align: right !important;
        }

        .footer-section {
            margin-top: 8px;
            font-size: 11px;
        }

        .footer-note {
            text-align: right;
            margin-bottom: 6px;
        }
    </style>
@endpush

@section('report-content')
    @php
        $opsMovement = is_array($opsMovement ?? null) ? $opsMovement : [];

        $adultTotal = (int) data_get($opsMovement, 'passengers.adult_total', 0);
        $officialTotal = (int) data_get($opsMovement, 'passengers.official_total', 0);
        $grandPaxTotal =
            (int) data_get($opsMovement, 'passengers.grand_total', 0) ?:
            $adultTotal +
                (int) data_get($opsMovement, 'passengers.child_total', 0) +
                (int) data_get($opsMovement, 'passengers.infant_total', 0) +
                $officialTotal;

        /*
        |----------------------------------------------------------------------
        | DEFAULT TEMPLATE ITEMS (max 3 per section)
        | Used only when no real data exists for that section.
        |----------------------------------------------------------------------
        */
        $defaultItems = [
            'manpower' => [
                ['item_name' => 'Mutawwif', 'unit_price' => 0, 'quantity' => 0, 'remarks' => ''],
                ['item_name' => 'Assisting Mutawwif', 'unit_price' => 0, 'quantity' => 0, 'remarks' => ''],
                ['item_name' => 'Mutawwif Meal', 'unit_price' => 0, 'quantity' => 0, 'remarks' => ''],
            ],
            'pettycash' => [
                ['item_name' => 'Hotel Porter', 'unit_price' => 0, 'quantity' => 0, 'remarks' => ''],
                ['item_name' => 'Bus Tipping', 'unit_price' => 0, 'quantity' => 0, 'remarks' => ''],
                ['item_name' => 'Tipping for Airport Porter', 'unit_price' => 0, 'quantity' => 0, 'remarks' => ''],
            ],
            'contingency' => [
                [
                    'item_name' => 'Contingency Fund',
                    'unit_price' => 0,
                    'quantity' => 0,
                    'remarks' => 'FUND IS TO BE USED SOLELY FOR OPS MATTER ONLY',
                ],
                ['item_name' => 'Emergency Reserve', 'unit_price' => 0, 'quantity' => 0, 'remarks' => ''],
                ['item_name' => 'Miscellaneous', 'unit_price' => 0, 'quantity' => 0, 'remarks' => ''],
            ],
        ];

        /*
        |----------------------------------------------------------------------
        | RESOLVE SECTIONS
        | Match dynamic budget data to the 3 fixed sections by key or title.
        | Falls back to template items if no data found.
        |----------------------------------------------------------------------
        */
        $budgetData = collect($opsMovement['budget'] ?? []);

        $resolveItems = function (array $slugs) use ($budgetData): array {
            $normalize = fn($v) => strtolower(str_replace([' ', '_', '-'], '', $v ?? ''));
            $matched = $budgetData->first(function ($s) use ($slugs, $normalize) {
                return in_array($normalize($s['key'] ?? ''), $slugs) || in_array($normalize($s['title'] ?? ''), $slugs);
            });
            return $matched['items'] ?? [];
        };

        $sections = [
            [
                'title' => 'Manpower Expenses',
                'items' => $resolveItems(['manpowerexpenses', 'manpower']) ?: $defaultItems['manpower'],
            ],
            [
                'title' => 'Petty Cash',
                'items' => $resolveItems(['pettycash']) ?: $defaultItems['pettycash'],
            ],
            [
                'title' => 'Contingency',
                'items' => $resolveItems(['contingency']) ?: $defaultItems['contingency'],
            ],
        ];

        // Append any extra sections from data that are not one of the 3 defaults
        $reservedSlugs = ['manpowerexpenses', 'manpower', 'pettycash', 'contingency'];
        $normalize = fn($v) => strtolower(str_replace([' ', '_', '-'], '', $v ?? ''));
        $extraSections = $budgetData
            ->filter(function ($s) use ($reservedSlugs, $normalize) {
                return !in_array($normalize($s['key'] ?? ($s['title'] ?? '')), $reservedSlugs);
            })
            ->values()
            ->toArray();

        $allSections = array_merge($sections, $extraSections);

        $budgetGrandTotal = collect($allSections)->sum(function ($section) {
            return collect($section['items'] ?? [])->sum(
                fn($item) => (float) ($item['unit_price'] ?? 0) * (float) ($item['quantity'] ?? 0),
            );
        });
    @endphp

    {{-- Summary Header --}}
    <table class="summary-grid">
        <tr>
            <th style="width: 12%;">Package Number</th>
            <td style="width: 21%;">{{ $opsMovement['package_number'] ?? '-' }}</td>
            <th style="width: 12%;">Manifest Number</th>
            <td style="width: 21%;">{{ $opsMovement['manifest_number'] ?? '-' }}</td>
            <th style="width: 12%;">Date Range</th>
            <td style="width: 22%;">{{ $opsMovement['departure_return_range'] ?? '-' }}</td>
        </tr>
        <tr>
            <th>No. of Jemaah</th>
            <td>{{ $adultTotal > 0 ? $adultTotal . ' adults' : $opsMovement['passengers']['grand_total'] ?? '-' }}</td>
            <th>No. of Officials</th>
            <td>{{ $officialTotal ?: $opsMovement['passengers']['official_total'] ?? '-' }}</td>
            <th>Mutawwif</th>
            <td>{{ $opsMovement['mutawwif_name'] ?? '-' }}</td>
        </tr>
        <tr>
            <th>Total Pax</th>
            <td>{{ $grandPaxTotal ?: '-' }}</td>
            <th colspan="4" style="font-size: 7.5px; font-weight: 400; color: #777; font-style: italic;">
                Official to indicate amount spent on remarks column. Be reminded to keep receipts if available.
            </th>
        </tr>
    </table>

    {{-- Budget Sections --}}
    @foreach ($allSections as $section)
        @php
            $items = collect($section['items'] ?? []);
            $sectionTotal = $items->sum(
                fn($item) => (float) ($item['unit_price'] ?? 0) * (float) ($item['quantity'] ?? 0),
            );
            $sectionTitle = $section['title'] ?? 'Budget Section';
        @endphp

        <div class="section-title">{{ $sectionTitle }}</div>
        <table class="section-table">
            <tr>
                <th style="width: 28%;">Items</th>
                <th style="width: 12%;" class="text-right">Unit Price</th>
                <th style="width: 10%;" class="text-right">Quantity</th>
                <th style="width: 18%;" class="text-right">Total (Saudi Riyal)</th>
                <th style="width: 32%;">Remarks</th>
            </tr>
            @forelse ($items as $item)
                @php
                    $unitPrice = (float) ($item['unit_price'] ?? 0);
                    $qty = (float) ($item['quantity'] ?? 0);
                    $lineTotal = $unitPrice * $qty;
                @endphp
                <tr>
                    <td>{{ $item['item_name'] ?? '-' }}</td>
                    <td class="text-right">{{ $unitPrice > 0 ? number_format($unitPrice, 2) : '' }}</td>
                    <td class="text-right">{{ $qty > 0 ? number_format($qty, 0) : '' }}</td>
                    <td class="text-right">{{ $lineTotal > 0 ? number_format($lineTotal, 2) : '' }}</td>
                    <td>{{ $item['remarks'] ?? '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center" style="color: #999; font-style: italic;">No items added yet.</td>
                </tr>
            @endforelse
            <tr>
                <th colspan="3" class="text-right">{{ $sectionTitle }} Budget (SAR)</th>
                <th class="text-right">SAR {{ number_format($sectionTotal, 2) }}</th>
                <th></th>
            </tr>
        </table>
    @endforeach

    {{-- Grand Total --}}
    <table class="section-table">
        <tr>
            <th colspan="3" class="text-right" style="font-size: 10px;">Grand Total (SAR)</th>
            <th class="text-right" style="font-size: 10px;">SAR {{ number_format($budgetGrandTotal, 2) }}</th>
            <th></th>
        </tr>
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
