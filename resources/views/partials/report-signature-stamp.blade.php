@php
    $mode = $branding['signature_stamp_layout'] ?? 'default';
    $showStamp = !empty($branding['show_stamp']);
    $showSignature = !empty($branding['show_signature']);
    $showQr = !empty($branding['show_qr']);
    $qrAlignmentValue = $branding['qr_alignment'] ?? 'center';
    $qrAlignment = in_array($qrAlignmentValue, ['left', 'center', 'right'], true)
        ? $qrAlignmentValue
        : 'center';
    $qrSourceAbsolute = $branding['qr_path_absolute'] ?? null;
    $qrSourceUrl = $branding['qr_url'] ?? null;

    // Always prefer custom paths (from "Configure Layout" dialog), fall back to regular paths.
    // The $mode variable is intentionally kept for the positioning/layout logic below.
    $stampSourceAbsolute =
        $branding['custom_stamp_path_absolute'] ??
        ($branding['stamp_path_absolute'] ?? null);
    $stampSourceUrl =
        $branding['custom_stamp_url'] ??
        ($branding['stamp_url'] ?? null);
    $signatureSourceAbsolute =
        $branding['custom_signature_path_absolute'] ??
        ($branding['signature_path_absolute'] ?? null);
    $signatureSourceUrl =
        $branding['custom_signature_url'] ??
        ($branding['signature_url'] ?? null);

    $layout = $branding['custom_signature_stamp_layout'] ?? [];
    $unit = ($layout['unit'] ?? 'percent') === 'px' ? 'px' : '%';
    $placement = $layout['placement'] ?? 'left_side';
    $stampLayout = $layout['stamp'] ?? ['width' => 26, 'height' => 58];
    $signatureLayout = $layout['signature'] ?? ['width' => 30, 'height' => 48];
    $labels = $layout['labels'] ?? [];
    $showNameDate =
        !empty($branding['show_signature_stamp_name']) &&
        !empty($branding['show_signature_stamp_date']);
    $fullName = $labels['full_name'] ?? $labels['signature_name'] ?? $labels['stamp_name'] ?? null;
    $displayDate = $labels['date'] ?? null;

    // Image sizes — px unit uses config values, percent falls back to fixed px
    $stampH = $unit === 'px' ? (($stampLayout['height'] ?? 58) . 'px') : '68px';
    $stampW = $unit === 'px' ? (($stampLayout['width'] ?? 68) . 'px') : 'auto';
    $sigH   = $unit === 'px' ? (($signatureLayout['height'] ?? 48) . 'px') : '50px';
    $sigW   = $unit === 'px' ? (($signatureLayout['width'] ?? 100) . 'px') : 'auto';

    // Placement determines flex direction and item order
    $isVertical = in_array($placement, ['up_side', 'down_side']);
    $isStacked = $placement === 'stack_each_other';
    // down_side = stamp below signature → signature renders first
    // right_side = stamp on right → signature renders first
    $signatureFirst = in_array($placement, ['down_side', 'right_side']);
    $flexDirection = $isVertical ? 'column' : 'row';
    $alignItems = 'flex-start';

    // Calculate stacked container height based on the larger image
    $stackedHeight = '68px';
    if ($isStacked && $unit === 'px') {
        $stampHeightNum = $stampLayout['height'] ?? 58;
        $sigHeightNum = $signatureLayout['height'] ?? 48;
        $stackedHeight = max($stampHeightNum, $sigHeightNum) . 'px';
    }
@endphp

@if ($showStamp || $showSignature || $showQr)
    <div style="display:block; margin-top:8px;">
        @if ($showQr)
            <div style="text-align:{{ $qrAlignment }}; margin-bottom:6px;">
                @if (($is_pdf ?? false) && !empty($qrSourceAbsolute) && file_exists($qrSourceAbsolute))
                    <img src="{{ $qrSourceAbsolute }}" alt="QR Code"
                        style="width:{{ $branding['qr_width'] ?? 120 }}px; height:auto; display:inline-block;">
                @elseif(!empty($qrSourceUrl))
                    <img src="{{ $qrSourceUrl }}" alt="QR Code"
                        style="width:{{ $branding['qr_width'] ?? 120 }}px; height:auto; display:inline-block;">
                @endif
            </div>
        @endif

        @if ($showStamp || $showSignature)
        <div style="{{ $isStacked ? 'position:relative; width:fit-content; height:' . $stackedHeight . ';' : 'display:flex; flex-direction:' . $flexDirection . '; align-items:' . $alignItems . '; gap:10px; width:fit-content;' }}">
            @if ($signatureFirst)
                @if ($showSignature)
                    <div style="{{ $isStacked ? 'position:absolute; top:0; left:0; z-index:2;' : '' }}">
                        @if (($is_pdf ?? false) && !empty($signatureSourceAbsolute) && file_exists($signatureSourceAbsolute))
                            <img src="{{ $signatureSourceAbsolute }}" alt="Authorised Signature"
                                style="height:{{ $sigH }}; width:{{ $sigW }}; object-fit:contain; display:block;">
                        @elseif(!empty($signatureSourceUrl))
                            <img src="{{ $signatureSourceUrl }}" alt="Authorised Signature"
                                style="height:{{ $sigH }}; width:{{ $sigW }}; object-fit:contain; display:block;">
                        @endif
                    </div>
                @endif
                @if ($showStamp)
                    <div style="{{ $isStacked ? 'position:absolute; top:0; left:0; z-index:1;' : '' }}">
                        @if (($is_pdf ?? false) && !empty($stampSourceAbsolute) && file_exists($stampSourceAbsolute))
                            <img src="{{ $stampSourceAbsolute }}" alt="Company Stamp"
                                style="height:{{ $stampH }}; width:{{ $stampW }}; object-fit:contain; display:block;">
                        @elseif(!empty($stampSourceUrl))
                            <img src="{{ $stampSourceUrl }}" alt="Company Stamp"
                                style="height:{{ $stampH }}; width:{{ $stampW }}; object-fit:contain; display:block;">
                        @endif
                    </div>
                @endif
            @else
                @if ($showStamp)
                    <div style="{{ $isStacked ? 'position:absolute; top:0; left:0; z-index:1;' : '' }}">
                        @if (($is_pdf ?? false) && !empty($stampSourceAbsolute) && file_exists($stampSourceAbsolute))
                            <img src="{{ $stampSourceAbsolute }}" alt="Company Stamp"
                                style="height:{{ $stampH }}; width:{{ $stampW }}; object-fit:contain; display:block;">
                        @elseif(!empty($stampSourceUrl))
                            <img src="{{ $stampSourceUrl }}" alt="Company Stamp"
                                style="height:{{ $stampH }}; width:{{ $stampW }}; object-fit:contain; display:block;">
                        @endif
                    </div>
                @endif
                @if ($showSignature)
                    <div style="{{ $isStacked ? 'position:absolute; top:0; left:0; z-index:2;' : '' }}">
                        @if (($is_pdf ?? false) && !empty($signatureSourceAbsolute) && file_exists($signatureSourceAbsolute))
                            <img src="{{ $signatureSourceAbsolute }}" alt="Authorised Signature"
                                style="height:{{ $sigH }}; width:{{ $sigW }}; object-fit:contain; display:block;">
                        @elseif(!empty($signatureSourceUrl))
                            <img src="{{ $signatureSourceUrl }}" alt="Authorised Signature"
                                style="height:{{ $sigH }}; width:{{ $sigW }}; object-fit:contain; display:block;">
                        @endif
                    </div>
                @endif
            @endif
        </div>
            @if ($showNameDate && (!empty($fullName) || !empty($displayDate)))
                <div style="margin-top:{{ $isStacked ? '4px' : '2px' }}; font-size:10px; line-height:1.2; display:flex; gap:12px; width:fit-content;">
                    @if (!empty($fullName))
                        <span>{{ $fullName }}</span>
                    @endif
                    @if (!empty($displayDate))
                        <span>{{ $displayDate }}</span>
                    @endif
                </div>
            @endif
        @endif
    </div>
@endif
