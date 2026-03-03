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
            // Per-module template configs (for use in settings page)
            'module_templates' => [
                'quotation' => $settings->getModuleTemplate('quotation'),
                'invoice' => $settings->getModuleTemplate('invoice'),
                'receipt' => $settings->getModuleTemplate('receipt'),
                'agreement' => $settings->getModuleTemplate('agreement'),
                'sales' => $settings->getModuleTemplate('sales'),
            ],
        ];
    }

    /**
     * Build report data structure by merging branding with body data.
     * Merges the specific module template settings into branding so
     * Blade views can access title_color, footer_text, show_stamp, show_signature.
     *
     * @param string $type Report type: 'invoice', 'quotation', 'receipt'
     * @param array $bodyData Module-specific report data
     * @return array Structured report data ready for rendering
     */
    public function build(string $type, array $bodyData): array
    {
        $settings = ReportSetting::current();
        $branding = $this->getBranding();

        // Merge the per-module template config into branding
        $moduleTemplate = $settings->getModuleTemplate($type);
        $branding = array_merge($branding, $moduleTemplate);

        return [
            'branding' => $branding,
            'type' => $type,
            'body' => $bodyData,
        ];
    }
}
