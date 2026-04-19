@props(['url'])
@php
    $reportLogoPath = \App\Models\ReportSetting::query()->value('logo_path');
    
    // Default URL to the primary logo
    $logoUrl = asset('logo-primary.png');

    // If there is a custom logo in storage, generate a public URL to it
    if ($reportLogoPath) {
        $logoUrl = asset('storage/' . $reportLogoPath);
    }
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ $logoUrl }}" class="logo" alt="Karva Travel and Tours Management System Logo" style="max-height: 75px; width: auto;">
</a>
</td>
</tr>
