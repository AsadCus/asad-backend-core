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
        $qrImage = $this->resolveAsset($settings->qr_image_path);
        $customStamp = $this->resolveAsset($settings->custom_stamp_path);
        $customSignature = $this->resolveAsset($settings->custom_signature_path);

        return [
            'company_name' => $settings->company_name,
            'company_address' => $settings->company_address,
            'company_phone' => $settings->company_phone,
            'company_email' => $settings->company_email,
            'page_margin_preset' => in_array($settings->page_margin_preset, ['narrow', 'normal', 'wide'], true)
                ? $settings->page_margin_preset
                : 'normal',
            'section_spacing_preset' => in_array($settings->section_spacing_preset, ['compact', 'normal', 'relaxed'], true)
                ? $settings->section_spacing_preset
                : 'normal',
            'signature_stamp_layout' => $settings->signature_stamp_layout ?? 'default',
            'custom_signature_stamp_layout' => $settings->custom_signature_stamp_layout,
            'qr_alignment' => in_array($settings->qr_alignment, ['left', 'center', 'right'], true)
                ? $settings->qr_alignment
                : 'center',
            'qr_width' => $settings->qr_width ?? 120,
            'qr_height' => $settings->qr_height ?? 120,
            // Relative URL versions (for web/frontend display) - works regardless of APP_URL or port
            'logo_url' => $logo['url'],
            'stamp_url' => $stamp['url'],
            'signature_url' => $signature['url'],
            'qr_url' => $qrImage['url'],
            'custom_stamp_url' => $customStamp['url'],
            'custom_signature_url' => $customSignature['url'],
            // Absolute path versions (for DomPDF)
            'logo_path_absolute' => $logo['absolute'],
            'stamp_path_absolute' => $stamp['absolute'],
            'signature_path_absolute' => $signature['absolute'],
            'qr_path_absolute' => $qrImage['absolute'],
            'custom_stamp_path_absolute' => $customStamp['absolute'],
            'custom_signature_path_absolute' => $customSignature['absolute'],
            'footer_text' => $settings->footer_text,
            // Per-module template configs (for use in settings page)
            'module_templates' => [
                'quotation' => $settings->getModuleTemplate('quotation'),
                'invoice' => $settings->getModuleTemplate('invoice'),
                'receipt' => $settings->getModuleTemplate('receipt'),
                'sales' => $settings->getModuleTemplate('sales'),
                'package' => $settings->getModuleTemplate('package'),
                'manifest_arabic_names' => $settings->getModuleTemplate('manifest_arabic_names'),
                'manifest_airline_names' => $settings->getModuleTemplate('manifest_airline_names'),
                'manifest_namelist_course_items' => $settings->getModuleTemplate('manifest_namelist_course_items'),
                'manifest_room_check' => $settings->getModuleTemplate('manifest_room_check'),
                'ops_movement' => $settings->getModuleTemplate('ops_movement'),
                'ops_movement_pif' => $settings->getModuleTemplate('ops_movement_pif'),
                'ops_movement_budget' => $settings->getModuleTemplate('ops_movement_budget'),
                'payment_summary' => $settings->getModuleTemplate('payment_summary'),
                'customer_receipts' => $settings->getModuleTemplate('customer_receipts'),
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
        $storedModule = $settings->module_templates[$type] ?? [];

        // Ensure boolean values are properly cast
        $moduleTemplate['show_stamp'] = (bool) ($moduleTemplate['show_stamp'] ?? false);
        $moduleTemplate['show_signature'] = (bool) ($moduleTemplate['show_signature'] ?? false);
        $moduleTemplate['show_qr'] = (bool) ($moduleTemplate['show_qr'] ?? true);
        $moduleTemplate['show_signature_stamp_name'] = (bool) ($moduleTemplate['show_signature_stamp_name'] ?? false);
        $moduleTemplate['show_signature_stamp_date'] = (bool) ($moduleTemplate['show_signature_stamp_date'] ?? false);

        $branding = array_merge($branding, $moduleTemplate);

        // Always inherit global signature_stamp_layout and custom layout config —
        // module templates do not override these; they only control show_stamp / show_signature flags.
        $branding['signature_stamp_layout'] = $settings->signature_stamp_layout ?? 'default';
        $branding['custom_signature_stamp_layout'] = $settings->custom_signature_stamp_layout;

        return [
            'branding' => $branding,
            'type' => $type,
            'body' => $bodyData,
        ];
    }
}
