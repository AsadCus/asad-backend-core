<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ReportTemplateUpdateRequest;
use App\Models\ReportSetting;
use App\Services\Report\ReportTemplateService;
use App\Services\UserRoleFileUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportTemplateController extends Controller
{
    private const FILE_KEY_MAP = [
        'logo_file' => 'logo_path',
        'stamp_file' => 'stamp_path',
        'signature_file' => 'signature_path',
    ];

    public function __construct(
        protected ReportTemplateService $reportTemplateService,
        protected UserRoleFileUploadService $fileUploadService,
    ) {
    }

    /**
     * Show the report template settings page.
     */
    public function index(): Response
    {
        $settings = ReportSetting::current();

        return Inertia::render('settings/report-template', [
            'settings' => [
                'id' => $settings->id,
                'company_name' => $settings->company_name,
                'company_address' => $settings->company_address,
                'company_phone' => $settings->company_phone,
                'company_email' => $settings->company_email,
                'logo_path' => $settings->logo_path,
                'footer_text' => $settings->footer_text,
                'stamp_path' => $settings->stamp_path,
                'signature_path' => $settings->signature_path,
                'module_templates' => [
                    'quotation' => $settings->getModuleTemplate('quotation'),
                    'invoice' => $settings->getModuleTemplate('invoice'),
                    'receipt' => $settings->getModuleTemplate('receipt'),
                    'agreement' => $settings->getModuleTemplate('agreement'),
                    'sales' => $settings->getModuleTemplate('sales'),
                ],
                'registered_modules' => $settings->registered_modules ?? [],
                'updated_by' => $settings->updated_by,
                'updated_at' => $settings->updated_at?->translatedFormat('d F Y H:i'),
            ],
            'branding' => $this->reportTemplateService->getBranding(),
        ]);
    }

    /**
     * Update the report template settings.
     */
    public function update(ReportTemplateUpdateRequest $request): RedirectResponse
    {
        $settings = ReportSetting::current();
        $validated = $request->validated();

        // Update basic fields
        $settings->update([
            'company_name' => $validated['company_name'],
            'company_address' => $validated['company_address'] ?? null,
            'company_phone' => $validated['company_phone'] ?? null,
            'company_email' => $validated['company_email'] ?? null,
            'footer_text' => $validated['footer_text'] ?? null,
            'updated_by' => auth()->id(),
        ]);

        // Update per-module template settings
        if (isset($validated['module_templates'])) {
            $existing = $settings->module_templates ?? [];
            $incoming = $validated['module_templates'];

            // Deep-merge: keep existing keys, overwrite with incoming
            foreach ($incoming as $moduleType => $moduleConfig) {
                $existing[$moduleType] = array_merge(
                    $existing[$moduleType] ?? ReportSetting::$moduleDefaults[$moduleType] ?? [],
                    $moduleConfig
                );
            }

            $settings->update(['module_templates' => $existing]);
        }

        // Process file uploads
        $this->fileUploadService->processUploads(
            model: $settings,
            data: $validated,
            fileKeyMap: self::FILE_KEY_MAP,
            baseDirectory: 'report',
            entityName: 'report-branding',
        );

        return back()->with('success', 'Report template settings updated successfully.');
    }

    /**
     * Register a new custom module.
     */
    public function storeModule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:64', 'alpha_dash', 'regex:/^[a-z0-9_]+$/'],
            'label' => ['required', 'string', 'max:64'],
            'document_type' => ['required', 'string', 'max:64'],
        ]);

        $settings = ReportSetting::current();

        // Prevent duplicates (hardcoded and custom)
        $hardcoded = array_keys(ReportSetting::$moduleDefaults);
        $existing = collect($settings->registered_modules ?? [])->pluck('key')->toArray();
        $taken = array_merge($hardcoded, $existing);

        if (in_array($validated['key'], $taken)) {
            return back()->withErrors(['key' => 'Module key already exists.']);
        }

        $modules = $settings->registered_modules ?? [];
        $modules[] = [
            'key' => $validated['key'],
            'label' => $validated['label'],
            'document_type' => strtoupper($validated['document_type']),
        ];

        $settings->update(['registered_modules' => $modules]);

        return back()->with('success', "Module '{$validated['label']}' added successfully.");
    }

    /**
     * Delete a custom module.
     */
    public function destroyModule(string $key): RedirectResponse
    {
        // Prevent deleting hardcoded modules
        if (array_key_exists($key, ReportSetting::$moduleDefaults)) {
            return back()->withErrors(['key' => 'Cannot delete a built-in module.']);
        }

        $settings = ReportSetting::current();

        // Remove from registered_modules list
        $modules = collect($settings->registered_modules ?? [])
            ->reject(fn($m) => $m['key'] === $key)
            ->values()
            ->toArray();

        // Also clean up its template settings
        $templates = $settings->module_templates ?? [];
        unset($templates[$key]);

        $settings->update([
            'registered_modules' => $modules,
            'module_templates' => $templates,
        ]);

        return back()->with('success', 'Module removed successfully.');
    }
}
