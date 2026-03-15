@extends('layout-report')

@section('document-title', 'Sales Profile - ' . ($data['name'] ?? 'Sales'))

@section('title-bar')
SALES PROFILE
@endsection

@push('styles')
    @@page {
        size: A4;
        margin: 1.2cm 1.5cm;
    }
    /* Sales uses 45/55 logo/info split */
    .logo-cell { width: 45%; vertical-align: top; }
    .info-cell { width: 55%; text-align: right; vertical-align: top; font-size: 10px; line-height: 1.5; }

    .logo-cell img {
        width: 240px;
        max-width: 240px;
        max-height: 90px;
        height: auto;
        object-fit: contain;
        display: block;
        margin: 0;
    }

    .info-cell b { font-size: 11px; }

    body { font-size: 11px; }

    /* Title Bar override for Sales */
    .title-bar { font-size: 14px; padding: 6px; letter-spacing: 3px; margin-bottom: 20px; }

    /* Content */
    .content-wrapper { padding: 0 20px; }

    /* Info Table */
    .info-section { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    .info-section td { padding: 8px 10px; border: 1px solid #ddd; vertical-align: top; }
    .info-section .label-cell { width: 35%; font-weight: bold; background-color: #f5f5f5; white-space: nowrap; }

    /* Footer */
    .footer-section { clear: both; padding: 15px 0 0; font-size: 10px; border-top: none; }
    .footer-note { text-align: center; margin-bottom: 10px; line-height: 1.4; }
@endpush

@section('report-content')

    <!-- Salesperson Details -->
    <div class="content-wrapper">
        <table class="info-section">
            <tr>
                <td class="label-cell">Name</td>
                <td>{{ $data['name'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label-cell">Email</td>
                <td>{{ $data['email'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label-cell">Contact</td>
                <td>{{ $data['contact'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label-cell">Branch</td>
                <td>{{ $data['branch_name'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label-cell">Registration Number</td>
                <td>{{ $data['registration_number'] ?? '-' }}</td>
            </tr>
        </table>

        <!-- Footer -->
        <div class="footer-section">
            @if (!empty($branding['footer_text']))
                <div class="footer-note">{!! nl2br(e($branding['footer_text'])) !!}</div>
            @endif

            @if (!empty($branding['show_stamp']))
                <div style="margin-top: 15px; margin-bottom: 0;">
                    @if (($is_pdf ?? false) && !empty($branding['stamp_path_absolute']) && file_exists($branding['stamp_path_absolute']))
                        <img src="{{ $branding['stamp_path_absolute'] }}" alt="Company Stamp"
                            style="max-height: 80px; width: auto; display: block;">
                    @elseif(!empty($branding['stamp_url']))
                        <img src="{{ $branding['stamp_url'] }}" alt="Company Stamp"
                            style="max-height: 80px; width: auto; display: block;">
                    @endif
                </div>
            @endif

            @if (!empty($branding['show_signature']))
                <div style="margin-top: 15px;">
                    <p style="font-size: 9px; margin: 0 0 5px 0;">Authorised Signature</p>
                    @if (($is_pdf ?? false) && !empty($branding['signature_path_absolute']) && file_exists($branding['signature_path_absolute']))
                        <img src="{{ $branding['signature_path_absolute'] }}" alt="Authorised Signature"
                            style="max-height: 60px; width: auto; display: block;">
                    @elseif(!empty($branding['signature_url']))
                        <img src="{{ $branding['signature_url'] }}" alt="Authorised Signature"
                            style="max-height: 60px; width: auto; display: block;">
                    @endif
                </div>
            @endif
        </div>
    </div>

@endsection
