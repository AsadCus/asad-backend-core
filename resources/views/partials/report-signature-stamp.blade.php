@php
    $mode = $branding['signature_stamp_layout'] ?? 'default';
    $showStamp = !empty($branding['show_stamp']);
    $showSignature = !empty($branding['show_signature']);

    $stampSourceAbsolute = $branding['stamp_path_absolute'] ?? null;
    $stampSourceUrl = $branding['stamp_url'] ?? null;
    $signatureSourceAbsolute = $branding['signature_path_absolute'] ?? null;
    $signatureSourceUrl = $branding['signature_url'] ?? null;

    if ($mode === 'custom') {
        $stampSourceAbsolute =
            $branding['custom_stamp_path_absolute'] ??
            ($branding['stamp_path_absolute'] ?? null);
        $stampSourceUrl = $branding['custom_stamp_url'] ?? ($branding['stamp_url'] ?? null);
        $signatureSourceAbsolute =
            $branding['custom_signature_path_absolute'] ??
            ($branding['signature_path_absolute'] ?? null);
        $signatureSourceUrl =
            $branding['custom_signature_url'] ?? ($branding['signature_url'] ?? null);
    }

    $layout = $branding['custom_signature_stamp_layout'] ?? [];
    $unit = ($layout['unit'] ?? 'percent') === 'px' ? 'px' : '%';
    $stampLayout = $layout['stamp'] ?? ['x' => 8, 'y' => 10, 'width' => 26, 'height' => 58, 'z' => 1];
    $signatureLayout = $layout['signature'] ?? ['x' => 62, 'y' => 18, 'width' => 30, 'height' => 48, 'z' => 2];
    $labels = $layout['labels'] ?? [];
    $showNameDate =
        !empty($branding['show_signature_stamp_name']) &&
        !empty($branding['show_signature_stamp_date']);
    $fullName = $labels['full_name']
        ?? $labels['signature_name']
        ?? $labels['stamp_name']
        ?? null;
    $displayDate = $labels['date'] ?? null;
@endphp

@if ($showStamp || $showSignature)
    @if ($mode === 'custom')
        @php
            $stampH = $unit === 'px' ? (($stampLayout['height'] ?? 58) . 'px') : '68px';
            $stampW = $unit === 'px' ? (($stampLayout['width'] ?? 68) . 'px') : 'auto';
            $sigH   = $unit === 'px' ? (($signatureLayout['height'] ?? 48) . 'px') : '50px';
            $sigW   = $unit === 'px' ? (($signatureLayout['width'] ?? 100) . 'px') : 'auto';
        @endphp
        <table class="stamp-sig-row" style="width:auto;">
            <tr>
                @if ($showStamp)
                    <td style="width:auto; padding-right:10px; vertical-align:bottom;">
                        @if (($is_pdf ?? false) && !empty($stampSourceAbsolute) && file_exists($stampSourceAbsolute))
                            <img src="{{ $stampSourceAbsolute }}" alt="Company Stamp"
                                style="height:{{ $stampH }}; width:{{ $stampW }}; object-fit:contain; display:block;">
                        @elseif(!empty($stampSourceUrl))
                            <img src="{{ $stampSourceUrl }}" alt="Company Stamp"
                                style="height:{{ $stampH }}; width:{{ $stampW }}; object-fit:contain; display:block;">
                        @endif
                    </td>
                @endif
                @if ($showSignature)
                    <td style="width:auto; text-align:left; vertical-align:bottom;">
                        <p style="font-size:10px; margin:0 0 3px 0;">Authorised Signature</p>
                        @if (($is_pdf ?? false) && !empty($signatureSourceAbsolute) && file_exists($signatureSourceAbsolute))
                            <img src="{{ $signatureSourceAbsolute }}" alt="Authorised Signature"
                                style="height:{{ $sigH }}; width:{{ $sigW }}; object-fit:contain; display:block;">
                        @elseif(!empty($signatureSourceUrl))
                            <img src="{{ $signatureSourceUrl }}" alt="Authorised Signature"
                                style="height:{{ $sigH }}; width:{{ $sigW }}; object-fit:contain; display:block;">
                        @endif
                    </td>
                @endif
            </tr>
            @if($showNameDate && (!empty($fullName) || !empty($displayDate)))
                <tr>
                    @if ($showStamp)
                        <td style="width:auto; font-size:10px; line-height:1.2; padding-top:2px; padding-right:10px;">
                            @if(!empty($fullName)) {{ $fullName }} @endif
                        </td>
                    @endif
                    @if ($showSignature)
                        <td style="width:auto; font-size:9px; line-height:1.2; text-align:left; padding-top:2px;">
                            @if(!empty($displayDate)) {{ $displayDate }} @endif
                        </td>
                    @endif
                </tr>
            @endif
        </table>
    @else
        <table class="stamp-sig-row stamp-sig-default" style="width:auto;">
            <tr>
                <td style="width:auto; padding-right:10px;">
                    @if ($showStamp)
                        @if (($is_pdf ?? false) && !empty($stampSourceAbsolute) && file_exists($stampSourceAbsolute))
                            <img src="{{ $stampSourceAbsolute }}" alt="Company Stamp"
                                style="height:68px; width:auto; display:block;">
                        @elseif(!empty($stampSourceUrl))
                            <img src="{{ $stampSourceUrl }}" alt="Company Stamp"
                                style="height:68px; width:auto; display:block;">
                        @endif
                    @endif
                </td>
                <td style="width:auto; text-align:left;">
                    @if ($showSignature)
                        <p style="font-size:10px; margin:0 0 3px 0;">Authorised Signature</p>
                        @if (($is_pdf ?? false) && !empty($signatureSourceAbsolute) && file_exists($signatureSourceAbsolute))
                            <img src="{{ $signatureSourceAbsolute }}" alt="Authorised Signature"
                                style="height:50px; width:auto; display:block;">
                        @elseif(!empty($signatureSourceUrl))
                            <img src="{{ $signatureSourceUrl }}" alt="Authorised Signature"
                                style="height:50px; width:auto; display:block;">
                        @endif
                    @endif
                </td>
            </tr>
            @if($showNameDate && (!empty($fullName) || !empty($displayDate)))
                <tr>
                    <td style="width:auto; font-size:10px; line-height:1.2; padding-top:2px; padding-right:10px;">
                        @if(!empty($fullName))
                            {{ $fullName }}
                        @endif
                    </td>
                    <td style="width:auto; font-size:9px; line-height:1.2; text-align:left; padding-top:2px;">
                        @if(!empty($displayDate))
                            {{ $displayDate }}
                        @endif
                    </td>
                </tr>
            @endif
        </table>
    @endif
@endif
