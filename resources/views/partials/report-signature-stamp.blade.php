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
        $mode === 'custom' &&
        !empty($branding['show_signature_stamp_name']) &&
        !empty($branding['show_signature_stamp_date']);
    $fullName =
        $labels['full_name'] ??
        ($labels['signature_name'] ?? ($labels['stamp_name'] ?? ($layout['signature']['name'] ?? ($layout['stamp']['name'] ?? null))));
    $displayDate = $labels['date'] ?? ($layout['signature']['date'] ?? null);
@endphp

@if ($showStamp || $showSignature)
    @if ($mode === 'custom')
        <div class="stamp-sig-row">
            <div class="stamp-sig-custom-box">
                @if ($showStamp)
                    <div class="stamp-sig-custom-item"
                        style="left: {{ $stampLayout['x'] ?? 8 }}{{ $unit }}; top: {{ $stampLayout['y'] ?? 10 }}{{ $unit }}; width: {{ $stampLayout['width'] ?? 26 }}{{ $unit }}; height: {{ $stampLayout['height'] ?? 58 }}{{ $unit }}; z-index: {{ $stampLayout['z'] ?? 1 }};">
                        @if (($is_pdf ?? false) && !empty($stampSourceAbsolute) && file_exists($stampSourceAbsolute))
                            <img src="{{ $stampSourceAbsolute }}" alt="Company Stamp"
                                style="height:100%; width:100%; object-fit:contain; display:block;">
                        @elseif(!empty($stampSourceUrl))
                            <img src="{{ $stampSourceUrl }}" alt="Company Stamp"
                                style="height:100%; width:100%; object-fit:contain; display:block;">
                        @endif
                    </div>
                @endif

                @if ($showSignature)
                    <div class="stamp-sig-custom-item"
                        style="left: {{ $signatureLayout['x'] ?? 62 }}{{ $unit }}; top: {{ $signatureLayout['y'] ?? 18 }}{{ $unit }}; width: {{ $signatureLayout['width'] ?? 30 }}{{ $unit }}; height: {{ $signatureLayout['height'] ?? 48 }}{{ $unit }}; z-index: {{ $signatureLayout['z'] ?? 2 }};">
                        @if (($is_pdf ?? false) && !empty($signatureSourceAbsolute) && file_exists($signatureSourceAbsolute))
                            <img src="{{ $signatureSourceAbsolute }}" alt="Authorised Signature"
                                style="height:100%; width:100%; object-fit:contain; display:block;">
                        @elseif(!empty($signatureSourceUrl))
                            <img src="{{ $signatureSourceUrl }}" alt="Authorised Signature"
                                style="height:100%; width:100%; object-fit:contain; display:block;">
                        @endif
                    </div>
                @endif

                @if($showNameDate && !empty($fullName))
                    <div style="position:absolute; left: {{ $stampLayout['x'] ?? 8 }}{{ $unit }}; top: calc({{ $stampLayout['y'] ?? 10 }}{{ $unit }} + {{ $stampLayout['height'] ?? 58 }}{{ $unit }} + 2px); width: {{ $stampLayout['width'] ?? 26 }}{{ $unit }}; font-size:10px; line-height:1.2; text-align:center;">
                        {{ $fullName }}
                    </div>
                @endif
                @if($showNameDate && !empty($displayDate))
                    <div style="position:absolute; left: {{ $signatureLayout['x'] ?? 62 }}{{ $unit }}; top: calc({{ $signatureLayout['y'] ?? 18 }}{{ $unit }} + {{ $signatureLayout['height'] ?? 48 }}{{ $unit }} + 2px); width: {{ $signatureLayout['width'] ?? 30 }}{{ $unit }}; font-size:9px; line-height:1.2; text-align:center;">
                        {{ $displayDate }}
                    </div>
                @endif
            </div>
        </div>
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
