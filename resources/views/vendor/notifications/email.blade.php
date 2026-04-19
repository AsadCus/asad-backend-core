<x-mail::message>
@php
    $mailBrandName = 'Karva Travel and Tours Management System';
    $isPasswordReset = isset($actionText) && str_contains(strtolower($actionText), 'reset');
@endphp

@if ($isPasswordReset)
<p style="margin: 0 0 14px; color: #a64a24; font-size: 13px; font-weight: 700; letter-spacing: 0.2px;">

</p>
<h2 style="margin: 0 0 12px; font-size: 21px; line-height: 1.35; color: #1f2937;">
Reset your password to continue your journey.
</h2>
@endif

{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# @lang('Whoops!')
@endif
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
<p style="margin: 0 0 14px; color: #4b5563; line-height: 1.7;">
{{ $line }}
</p>

@endforeach

{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
<p style="margin: 0 0 8px; color: #9a6a56; font-size: 13px;">
&#128205; Need help? Reach us through our official support channel.
</p>

@if (! empty($salutation))
{{ $salutation }}
@else
@lang('Regards,')<br>
{{ $mailBrandName }}
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
@php
    $maxLength = 50;
    $displayUrl = strlen($displayableActionUrl) > $maxLength
        ? substr($displayableActionUrl, 0, $maxLength) . '…'
        : $displayableActionUrl;
@endphp

@lang(
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n" .
    'into your web browser:',
    ['actionText' => $actionText]
)
<span class="break-all">
    [{{ $displayUrl }}]({{ $actionUrl }})
</span>
</x-slot:subcopy>
@endisset
</x-mail::message>

