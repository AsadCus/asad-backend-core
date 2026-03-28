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
            margin-bottom: 6px;
        }
    </style>
@endpush

@section('report-content')
    @php
        $opsMovement = is_array($opsMovement ?? null) ? $opsMovement : [];
        $budgetSections = collect($opsMovement['budget'] ?? []);
        $budgetGrandTotal = $budgetSections->sum(function ($section) {
            return collect($section['items'] ?? [])->sum(function ($item) {
                return (float) ($item['unit_price'] ?? 0) * (float) ($item['quantity'] ?? 0);
            });
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
            <td>{{ $opsMovement['passengers']['grand_total'] ?? '-' }}</td>
            <th>No. of Officials</th>
            <td>{{ $opsMovement['passengers']['official_total'] ?? '-' }}</td>
            <th>Mutawwif</th>
            <td>{{ $opsMovement['mutawwif_name'] ?? '-' }}</td>
        </tr>
    </table>

    {{-- Budget Sections --}}
    @forelse ($budgetSections as $section)
        @php
            $items = collect($section['items'] ?? []);
            $sectionTotal = $items->sum(
                fn($item) => (float) ($item['unit_price'] ?? 0) * (float) ($item['quantity'] ?? 0),
            );
        @endphp

        <div class="section-title">{{ $section['title'] ?? 'Budget Section' }}</div>
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
                    $lineTotal = (float) ($item['unit_price'] ?? 0) * (float) ($item['quantity'] ?? 0);
                @endphp
                <tr>
                    <td>{{ $item['item_name'] ?? '-' }}</td>
                    <td class="text-right">{{ number_format((float) ($item['unit_price'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($item['quantity'] ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format($lineTotal, 2) }}</td>
                    <td>{{ $item['remarks'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">No budget item.</td>
                </tr>
            @endforelse
            <tr>
                <th colspan="3" class="text-right">{{ $section['title'] ?? 'Section' }} Budget (SAR)</th>
                <th class="text-right">{{ number_format($sectionTotal, 2) }}</th>
                <th></th>
            </tr>
        </table>
    @empty
        <div class="section-title">Budget</div>
        <table class="section-table">
            <tr>
                <td colspan="5" class="text-center">No budget data.</td>
            </tr>
        </table>
    @endforelse

    {{-- Grand Total --}}
    <table class="section-table">
        <tr>
            <th colspan="3" class="text-right" style="font-size: 10px;">Grand Total (SAR)</th>
            <th class="text-right" style="font-size: 10px;">{{ number_format($budgetGrandTotal, 2) }}</th>
            <th></th>
        </tr>
    </table>

    <div class="footer-section">
        @if (!empty($branding['footer_text']))
            <div class="footer-note">{!! nl2br(e($branding['footer_text'])) !!}</div>
        @endif

        @include('partials.report-signature-stamp')
    </div>
@endsection
