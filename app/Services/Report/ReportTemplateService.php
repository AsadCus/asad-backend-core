<?php

namespace App\Services\Report;

use App\Models\ReportSetting;

class ReportTemplateService
{
    /**
     * Resolve image URL and absolute path for report assets.
     * Prefers storage uploads, then falls back to public assets.
     *
     * @return array{url: string|null, absolute: string|null}
     */
    private function resolveAsset(?string $path, ?string $defaultPublicFile = null): array
    {
        $candidatePath = $path ?: $defaultPublicFile;

        if (! $candidatePath) {
            return ['url' => null, 'absolute' => null];
        }

        $storageAbsolute = storage_path('app/public/'.$candidatePath);
        if (file_exists($storageAbsolute)) {
            return [
                'url' => '/storage/'.$candidatePath,
                'absolute' => $storageAbsolute,
            ];
        }

        $publicAbsolute = public_path($candidatePath);
        if (file_exists($publicAbsolute)) {
            return [
                'url' => '/'.$candidatePath,
                'absolute' => $publicAbsolute,
            ];
        }

        return ['url' => null, 'absolute' => null];
    }

    /**
     * Get current branding settings with resolved URLs.
     */
    public function getBranding(): array
    {
        $settings = ReportSetting::current();
        $logo = $this->resolveAsset($settings->logo_path, 'logo-primary.png');
        $stamp = $this->resolveAsset($settings->stamp_path);
        $signature = $this->resolveAsset($settings->signature_path);

        return [
            'company_name' => $settings->company_name,
            'company_address' => $settings->company_address,
            'company_phone' => $settings->company_phone,
            'company_email' => $settings->company_email,
            // Relative URL versions (for web/frontend display) - works regardless of APP_URL or port
            'logo_url' => $logo['url'],
            'stamp_url' => $stamp['url'],
            'signature_url' => $signature['url'],
            // Absolute path versions (for DomPDF)
            'logo_path_absolute' => $logo['absolute'],
            'stamp_path_absolute' => $stamp['absolute'],
            'signature_path_absolute' => $signature['absolute'],
            'footer_text' => $settings->footer_text,
            // Per-module template configs (for use in settings page)
            'module_templates' => [
                'quotation' => $settings->getModuleTemplate('quotation'),
                'invoice' => $settings->getModuleTemplate('invoice'),
                'receipt' => $settings->getModuleTemplate('receipt'),
                'sales' => $settings->getModuleTemplate('sales'),
                'package' => $settings->getModuleTemplate('package'),
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
