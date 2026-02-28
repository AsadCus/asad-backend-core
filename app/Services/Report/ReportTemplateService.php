<?php

namespace App\Services\Report;

use App\Models\ReportSetting;
use Illuminate\Support\Facades\Storage;

class ReportTemplateService
{
    /**
     * Get current branding settings with resolved URLs.
     */
    public function getBranding(): array
    {
        $settings = ReportSetting::current();

        return [
            'company_name' => $settings->company_name,
            'company_address' => $settings->company_address,
            'company_phone' => $settings->company_phone,
            'company_email' => $settings->company_email,
            'logo_url' => $settings->logo_path 
                ? Storage::disk('public')->url($settings->logo_path) 
                : null,
            'footer_text' => $settings->footer_text,
            'stamp_url' => $settings->stamp_path 
                ? Storage::disk('public')->url($settings->stamp_path) 
                : null,
            'signature_url' => $settings->signature_path 
                ? Storage::disk('public')->url($settings->signature_path) 
                : null,
        ];
    }

    /**
     * Build report data structure by merging branding with body data.
     *
     * @param string $type Report type: 'invoice', 'quotation', 'receipt'
     * @param array $bodyData Module-specific report data
     * @return array Structured report data ready for rendering
     */
    public function build(string $type, array $bodyData): array
    {
        return [
            'branding' => $this->getBranding(),
            'type' => $type,
            'body' => $bodyData,
        ];
    }
}
