@extends('layout-report')

@section('document-title', 'Invoice - ' . ($data['invoice_number'] ?? 'INV-0001'))

@section('extra-company-reg')
    @if ($data['sales_registration_number'] ?? false)
        REGISTRATION NO. {{ $data['sales_registration_number'] }}&nbsp;&nbsp;
    @endif
@endsection

@section('title-bar')
    INVOICE
@endsection


@push('styles')
    <style>
        /* ── Content Wrapper ── */
        .content-wrapper {
            padding: 0;
        }

        /* ── Order Info ── */
        .order-info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .order-info-grid td {
            vertical-align: top;
            padding: 1px 0;
            font-size: 11px;
        }

        .lbl {
            font-weight: bold;
            white-space: nowrap;
            width: 105px;
        }

        .sep {
            width: 12px;
        }

        .lbl-r {
            font-weight: bold;
            white-space: nowrap;
            width: 90px;
        }

        /* ── Items Table ── */
        .items-section {
            border-top: 1.5px solid #333;
            border-bottom: 1.5px solid #333;
            margin-bottom: 8px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table thead th {
            border-bottom: 1px solid #333;
            padding: 3px 0;
            font-weight: bold;
            font-size: 11px;
            text-align: left;
        }

        .items-table thead th.col-rate,
        .items-table thead th.col-total {
            text-align: right;
        }

        .items-table td {
            padding: 3px 0;
            vertical-align: top;
            font-size: 12px;
        }

        .col-desc {
            width: auto;
        }

        .col-rate {
            width: 80px;
            text-align: right;
            white-space: nowrap;
        }

        .col-total {
            width: 90px;
            text-align: right;
            white-space: nowrap;
        }

        /* Sub-item indent */
        .sub-item .col-desc {
            padding-left: 20px;
        }

        /* ── Totals ── */
        .totals-wrapper {
            width: 100%;
            text-align: right;
            padding: 4px 0 2px;
            margin-top: 6px;
        }

        .totals-table {
            width: 320px;
            display: inline-table;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .totals-table td {
            padding: 1.5px 0;
        }

        .total-label {
            width: 68%;
            text-align: right;
            font-size: 11px;
            color: #555;
        }

        .total-amount {
            width: 32%;
            text-align: right;
            font-weight: bold;
            font-size: 12px;
            white-space: nowrap;
        }

        .totals-table .total-row-grand td {
            border-top: 1px solid #cfcfcf;
            padding-top: 4px;
        }

        .footer-section {
            padding: 12px 0 0;
            font-size: 10px;
            border-top: none;
        }

        .footer-note {
            text-align: right;
            margin-bottom: 8px;
            line-height: 1.5;
            color: #333;
        }
    </style>
@endpush

@section('report-content')

    <div class="content-wrapper">

        {{-- ── ORDER INFO ── --}}
        <table class="order-info-grid">
            <tr>
                {{-- Left: Customer details --}}
                <td width="58%">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td class="lbl">Customer No.</td>
                            <td class="sep">:</td>
                            <td>{{ $data['customer_number'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Customer</td>
                            <td class="sep">:</td>
                            <td>{{ $data['customer_name'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl" style="vertical-align:top;">Address</td>
                            <td class="sep" style="vertical-align:top;">:</td>
                            <td style="white-space: pre-line;">{{ $data['customer_address'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Contact</td>
                            <td class="sep">:</td>
                            <td>{{ $data['customer_contact'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Email</td>
                            <td class="sep">:</td>
                            <td>{{ $data['customer_email'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Description</td>
                            <td class="sep">:</td>
                            <td>{{ $data['description'] ?? '-' }}</td>
                        </tr>
                    </table>
                </td>
                {{-- Right: Invoice meta --}}
                <td width="42%">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td class="lbl-r">Order No.</td>
                            <td class="sep">:</td>
                            <td>{{ $data['order_number'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl-r">Invoice No.</td>
                            <td class="sep">:</td>
                            <td>{{ $data['invoice_number'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl-r">Invoice Date</td>
                            <td class="sep">:</td>
                            <td>{{ $data['invoice_date'] ?? date('d/m/Y') }}</td>
                        </tr>
                        <tr>
                            <td class="lbl-r">Due Date</td>
                            <td class="sep">:</td>
                            <td>
                                {{ $data['due_date'] ?? '-' }}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        @php
            function formatCurrency($value)
            {
                return \App\Helpers\FormatService::formatCurrency($value);
            }

            function toAlphabet($num)
            {
                $alphabet = 'abcdefghijklmnopqrstuvwxyz';
                if ($num - 1 < 26) {
                    return $alphabet[$num - 1];
                }
                $firstLetter = $alphabet[floor(($num - 1) / 26) - 1];
                $secondLetter = $alphabet[($num - 1) % 26];
                return $firstLetter . $secondLetter;
            }

            function hasChildren($item, $allItems)
            {
                return collect($allItems)
                    ->filter(function ($child) use ($item) {
                        $parentIdMatch = !empty($child['parent_id']) && $child['parent_id'] == $item['id'];
                        $parentKeyMatch =
                            !empty($child['parent_key']) &&
                            !empty($item['_key']) &&
                            $child['parent_key'] == $item['_key'];
                        return $parentIdMatch || $parentKeyMatch;
                    })
                    ->isNotEmpty();
            }

            function renderItem($item, $allItems, $level = 0, &$counter, &$subtotal, $subCounter = 0)
            {
                $itemNumber = '';
                if ($level === 0) {
                    $counter++;
                    $itemNumber = $counter . '.';
                } elseif ($level === 1) {
                    $itemNumber = toAlphabet($subCounter) . '.';
                }

                $indentClass = $level > 0 ? 'sub-item' : '';

                $rate = floatval($item['rate'] ?? 0);
                $qty = floatval($item['quantity'] ?? 1);
                $itemTotal = $rate * $qty;

                if (!$item['is_header']) {
                    $subtotal += $itemTotal;
                }

                $descriptionText = e($item['description']);

                $children = collect($allItems)
                    ->filter(function ($child) use ($item) {
                        $parentIdMatch = !empty($child['parent_id']) && $child['parent_id'] == $item['id'];
                        $parentKeyMatch =
                            !empty($child['parent_key']) &&
                            !empty($item['_key']) &&
                            $child['parent_key'] == $item['_key'];
                        return $parentIdMatch || $parentKeyMatch;
                    })
                    ->sortBy('sort_order');

                $rateDisplay = !$item['is_header'] && $rate ? formatCurrency($rate) : '';
                $totalDisplay = !$item['is_header'] && $itemTotal ? formatCurrency($itemTotal) : '';

                echo '<tr class="item-row ' . $indentClass . '">';
                echo '<td class="col-desc">' . $itemNumber . ' ' . $descriptionText . '</td>';
                echo '<td class="col-rate">' . $rateDisplay . '</td>';
                echo '<td class="col-total">' . $totalDisplay . '</td>';
                echo '</tr>';

                $childSubCounter = 0;
                foreach ($children as $child) {
                    $childSubCounter++;
                    renderItem($child, $allItems, $level + 1, $counter, $subtotal, $childSubCounter);
                }
            }

            $counter = 0;
            $subtotal = 0;

            $rootItems = collect($items ?? [])
                ->filter(fn($item) => empty($item['parent_id']) && empty($item['parent_key']))
                ->sortBy('sort_order');
        @endphp

        {{-- ── ITEMS ── --}}
        <div class="items-section">
            <table class="items-table">
                <thead>
                    <tr>
                        <th class="col-desc">Item Description</th>
                        <th class="col-rate">Cost</th>
                        <th class="col-total">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rootItems as $item)
                        @php renderItem($item, $items, 0, $counter, $subtotal, 0); @endphp
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="totals-wrapper">
            <table class="totals-table">
                <tbody>
                    <tr>
                        <td class="total-label">Sub Total:</td>
                        <td class="total-amount">{{ formatCurrency($data['subtotal_amount'] ?? $subtotal) }}</td>
                    </tr>
                    @if (!empty($data['extensions']) && count($data['extensions']) > 0)
                        @foreach ($data['extensions'] as $extension)
                            <tr>
                                <td class="total-label">{{ $extension['name'] ?? 'Extension' }}:</td>
                                <td class="total-amount">{{ formatCurrency($extension['amount'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    @endif
                    <tr class="total-row-grand">
                        <td class="total-label">Total Amount:</td>
                        <td class="total-amount">{{ formatCurrency($data['total_amount'] ?? $subtotal) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── FOOTER ── --}}
    <div class="footer-section">
        {{-- Compute active notes so variable is in scope for both the partial and footer condition --}}
        @php
            $activeNotes = collect($data['notes'] ?? [])
                ->filter(fn($n) => !empty(trim(strip_tags($n['description'] ?? ''))))
                ->sortBy('sort_order')
                ->values();
        @endphp

        {{-- Notes: rendered above footer if any description is filled; Tiptap HTML preserves alignment/formatting --}}
        @include('partials.report-notes')

        {{-- Module footer text from Report Template Settings --}}
        {{-- When no notes: always show footer_text (centered) or fallback message (centered) --}}
        @if (!empty($branding['footer_text']))
            <div class="footer-note" style="text-align:{{ $activeNotes->isEmpty() ? 'center' : 'right' }}">{!! nl2br(e($branding['footer_text'])) !!}</div>
        @elseif ($activeNotes->isEmpty())
            <div class="footer-note" style="text-align:center">Thank you for your business!</div>
        @endif

        @include('partials.report-signature-stamp')
    </div>


    </div>

@endsection
