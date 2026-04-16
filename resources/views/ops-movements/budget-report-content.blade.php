@extends('layout-report')

@section('document-title', 'Ops Movement Budget - ' . ($opsMovement['package_number'] ?? 'Ops Movement'))

@section('title-bar')
    BUDGET PLAN
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
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 14px 0 6px;
            padding: 4px 8px;
            background: #f0f0f0;
            border-left: 3px solid {{ $branding['title_color'] ?? '#40A09D' }};
            color: #222;
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

        $allSections = collect($opsMovement['budget'] ?? [])->values()->all();

        $budgetGrandTotal = collect($allSections)->sum(function ($section) {
            $sectionSubtotal = collect($section['items'] ?? [])->sum(
                fn($item) => (float) ($item['unit_price'] ?? 0) * (float) ($item['quantity'] ?? 0),
            );

            $sectionExtensionTotal = collect($section['extensions'] ?? [])->sum(function ($extension) use ($sectionSubtotal) {
                $extensionMode = strtolower((string) ($extension['calculation_mode'] ?? 'fixed'));
                $extensionValue = (float) ($extension['calculation_value'] ?? 0);

                return $extensionMode === 'percentage'
                    ? ($sectionSubtotal * $extensionValue) / 100
                    : $extensionValue;
            });

            return $sectionSubtotal + $sectionExtensionTotal;
        });
        $mutawwifNames = collect($opsMovement['officials'] ?? [])
            ->filter(function ($official) {
                $rawType = strtolower(trim((string) ($official['type'] ?? '')));
                $normalized = preg_replace('/[^a-z]/', '', $rawType) ?? '';

                return str_starts_with($normalized, 'mutawif') || str_starts_with($normalized, 'mutawwif');
            })
            ->map(fn($official) => trim((string) ($official['name'] ?? '')))
            ->filter(fn($name) => $name !== '')
            ->values()
            ->all();

        $budgetCurrency = $opsMovement['budget_currency'] ?? 'SAR';
    @endphp

    {{-- Summary Header --}}
    <table class="summary-grid">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                    <tr>
                        <th style="width: 34%;">Package Number</th>
                        <td>{{ $opsMovement['package_number'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Manifest Number</th>
                        <td>{{ $opsMovement['manifest_number'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Date Range</th>
                        <td>{{ $opsMovement['departure_return_range'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td colspan="2" style="font-size: 7.5px; font-weight: 400; color: #777; font-style: italic;">
                            Official to indicate amount spent on remarks column. Be reminded to keep receipts if available.
                        </td>
                    </tr>
                    
                </table>
            </td>
            <td style="width: 50%; vertical-align: top;">
                <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                    <tr>
                        <th>No. of Jemaah</th>
                        <td>{{ $adultTotal > 0 ? $adultTotal : $opsMovement['passengers']['grand_total'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th style="width: 38%;">No. of Officials</th>
                        <td>{{ $officialTotal ?: $opsMovement['passengers']['official_total'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Mutawwif/Mutawwifah</th>
                        <td>{{ count($mutawwifNames) > 0 ? implode(', ', $mutawwifNames) : '-' }}</td>
                    </tr>
                    {{-- <tr>
                        <th>No. of Mutawwif/Mutawwifah</th>
                        <td>{{ count($mutawwifNames) > 0 ? count($mutawwifNames) : '-' }}</td>
                    </tr> --}}
                    <tr>
                        <th>Total Pax</th>
                        <td>{{ $grandPaxTotal ?: '-' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Budget Sections --}}
    @foreach ($allSections as $section)
        @php
            $items = collect($section['items'] ?? []);
            $extensions = collect($section['extensions'] ?? []);
            $sectionSubtotal = $items->sum(
                fn($item) => (float) ($item['unit_price'] ?? 0) * (float) ($item['quantity'] ?? 0),
            );
            $sectionExtensionTotal = $extensions->sum(function ($extension) use ($sectionSubtotal) {
                $extensionMode = strtolower((string) ($extension['calculation_mode'] ?? 'fixed'));
                $extensionValue = (float) ($extension['calculation_value'] ?? 0);

                return $extensionMode === 'percentage'
                    ? ($sectionSubtotal * $extensionValue) / 100
                    : $extensionValue;
            });
            $sectionTotal = $sectionSubtotal + $sectionExtensionTotal;
            $sectionTitle = $section['title'] ?? 'Budget Section';
        @endphp

        <div class="section-title">{{ $sectionTitle }}</div>
        <table class="section-table">
            <tr>
                <th style="width: 28%;">Items</th>
                <th style="width: 12%;" class="text-right">Unit Price</th>
                <th style="width: 10%;" class="text-right">Quantity</th>
                <th style="width: 18%;" class="text-right">Total ({{ $budgetCurrency }})</th>
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
                <th colspan="3" class="text-right">Sub Total ({{ $budgetCurrency }})</th>
                <th class="text-right">{{ $budgetCurrency }} {{ number_format($sectionSubtotal, 2) }}</th>
                <th></th>
            </tr>
            @foreach ($extensions as $extension)
                @php
                    $extensionMode = strtolower((string) ($extension['calculation_mode'] ?? 'fixed'));
                    $extensionValue = (float) ($extension['calculation_value'] ?? 0);
                    $extensionAmount = $extensionMode === 'percentage'
                        ? ($sectionSubtotal * $extensionValue) / 100
                        : $extensionValue;
                    $extensionName = trim((string) ($extension['name'] ?? 'Extension'));
                    $extensionLabel = $extensionMode === 'percentage'
                        ? sprintf('%s %s%%', $extensionName, number_format($extensionValue, 2))
                        : $extensionName;
                @endphp
                <tr>
                    <td colspan="3" class="text-right">{{ $extensionLabel }}</td>
                    <td class="text-right">{{ $budgetCurrency }} {{ number_format($extensionAmount, 2) }}</td>
                    <td></td>
                </tr>
            @endforeach
            <tr>
                <th colspan="3" class="text-right">Total ({{ $budgetCurrency }})</th>
                <th class="text-right">{{ $budgetCurrency }} {{ number_format($sectionTotal, 2) }}</th>
                <th></th>
            </tr>
        </table>
    @endforeach

    {{-- Grand Total --}}
    <table class="section-table">
        <tr>
            <th colspan="3" class="text-right" style="width: 50%; font-size: 10px;">Grand Total ({{ $budgetCurrency }})</th>
            <th class="text-right" style="width: 18%; font-size: 10px;">{{ $budgetCurrency }} {{ number_format($budgetGrandTotal, 2) }}</th>
            <th style="width: 32%;"></th>
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
