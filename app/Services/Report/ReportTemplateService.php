<?php

namespace App\Services\Report;

use App\Models\ReportSetting;

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
            // Relative URL versions (for web/frontend display) - works regardless of APP_URL or port
            'logo_url' => $settings->logo_path
                ? '/storage/'.$settings->logo_path
                : null,
            'stamp_url' => $settings->stamp_path
                ? '/storage/'.$settings->stamp_path
                : null,
            'signature_url' => $settings->signature_path
                ? '/storage/'.$settings->signature_path
                : null,
            // Absolute path versions (for DomPDF)
            'logo_path_absolute' => $settings->logo_path
                ? storage_path('app/public/'.$settings->logo_path)
                : null,
            'stamp_path_absolute' => $settings->stamp_path
                ? storage_path('app/public/'.$settings->stamp_path)
                : null,
            'signature_path_absolute' => $settings->signature_path
                ? storage_path('app/public/'.$settings->signature_path)
                : null,
            'footer_text' => $settings->footer_text,
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
     * @param  string  $type  Report type: 'invoice', 'quotation', 'receipt'
     * @param  array  $bodyData  Module-specific report data
     * @return array Structured report data ready for rendering
     */
    public function build(string $type, array $bodyData): array
    {
        $settings = ReportSetting::current();
        $branding = $this->getBranding();

        // Merge the per-module template config into branding
        $moduleTemplate = $settings->getModuleTemplate($type);
        
        // Ensure boolean values are properly cast
        $moduleTemplate['show_stamp'] = (bool) ($moduleTemplate['show_stamp'] ?? false);
        $moduleTemplate['show_signature'] = (bool) ($moduleTemplate['show_signature'] ?? false);
        
        $branding = array_merge($branding, $moduleTemplate);

        return [
            'branding' => $branding,
            'type' => $type,
            'body' => $bodyData,
        ];
    }
}
