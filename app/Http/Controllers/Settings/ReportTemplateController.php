<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ReportTemplateUpdateRequest;
use App\Models\ReportSetting;
use App\Services\Report\ReportTemplateService;
use App\Services\UserRoleFileUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ReportTemplateController extends Controller
{
    private const FILE_KEY_MAP = [
        'logo_file' => 'logo_path',
        'qr_file' => 'qr_image_path',
        'stamp_file' => 'stamp_path',
        'signature_file' => 'signature_path',
        'custom_stamp_file' => 'custom_stamp_path',
        'custom_signature_file' => 'custom_signature_path',
    ];

    private const ALLOWED_PLACEMENTS = [
        'left_side',
        'right_side',
        'stack_each_other',
        'up_side',
        'down_side',
    ];

    private const ALLOWED_MARGIN_PRESETS = ['narrow', 'normal', 'wide'];

    private const ALLOWED_SECTION_SPACING_PRESETS = ['compact', 'normal', 'relaxed'];

    private const PRESET_LAYOUTS = [
        'percent' => [
            'left_side' => [
                'stamp' => ['x' => 2, 'y' => 20, 'width' => 24, 'height' => 52, 'z' => 1],
                'signature' => ['x' => 28, 'y' => 24, 'width' => 28, 'height' => 46, 'z' => 2],
            ],
            'right_side' => [
                'stamp' => ['x' => 28, 'y' => 20, 'width' => 24, 'height' => 52, 'z' => 2],
                'signature' => ['x' => 2, 'y' => 24, 'width' => 28, 'height' => 46, 'z' => 1],
            ],
            'stack_each_other' => [
                'stamp' => ['x' => 8, 'y' => 18, 'width' => 28, 'height' => 54, 'z' => 1],
                'signature' => ['x' => 12, 'y' => 22, 'width' => 30, 'height' => 46, 'z' => 2],
            ],
            'up_side' => [
                'stamp' => ['x' => 6, 'y' => 4, 'width' => 26, 'height' => 38, 'z' => 2],
                'signature' => ['x' => 6, 'y' => 44, 'width' => 30, 'height' => 38, 'z' => 1],
            ],
            'down_side' => [
                'stamp' => ['x' => 6, 'y' => 44, 'width' => 26, 'height' => 38, 'z' => 2],
                'signature' => ['x' => 6, 'y' => 4, 'width' => 30, 'height' => 38, 'z' => 1],
            ],
        ],
        'px' => [
            'left_side' => [
                'stamp' => ['x' => 8, 'y' => 36, 'width' => 112, 'height' => 80, 'z' => 1],
                'signature' => ['x' => 126, 'y' => 42, 'width' => 132, 'height' => 72, 'z' => 2],
            ],
            'right_side' => [
                'stamp' => ['x' => 126, 'y' => 36, 'width' => 108, 'height' => 80, 'z' => 2],
                'signature' => ['x' => 8, 'y' => 42, 'width' => 130, 'height' => 72, 'z' => 1],
            ],
            'stack_each_other' => [
                'stamp' => ['x' => 16, 'y' => 36, 'width' => 124, 'height' => 84, 'z' => 1],
                'signature' => ['x' => 32, 'y' => 44, 'width' => 138, 'height' => 72, 'z' => 2],
            ],
            'up_side' => [
                'stamp' => ['x' => 8, 'y' => 8, 'width' => 112, 'height' => 62, 'z' => 2],
                'signature' => ['x' => 8, 'y' => 78, 'width' => 140, 'height' => 62, 'z' => 1],
            ],
            'down_side' => [
                'stamp' => ['x' => 8, 'y' => 78, 'width' => 112, 'height' => 62, 'z' => 2],
                'signature' => ['x' => 8, 'y' => 8, 'width' => 140, 'height' => 62, 'z' => 1],
            ],
        ],
    ];

    public function __construct(
        protected ReportTemplateService $reportTemplateService,
        protected UserRoleFileUploadService $fileUploadService,
    ) {}

    /**
     * Show the report template settings page.
     */
    public function index(): Response
    {
        $settings = ReportSetting::current();

        // Build module templates for all modules (built-in + custom)
        $moduleTemplates = [];

        // Add built-in modules
        foreach (array_keys(ReportSetting::$moduleDefaults) as $moduleKey) {
            $moduleTemplates[$moduleKey] = $settings->getModuleTemplate($moduleKey);
        }

        // Add custom registered modules
        foreach ($settings->registered_modules ?? [] as $module) {
            $moduleTemplates[$module['key']] = $settings->getModuleTemplate($module['key']);
        }

        return Inertia::render('settings/report-template', [
            'settings' => [
                'id' => $settings->id,
                'company_name' => $settings->company_name,
                'company_address' => $settings->company_address,
                'company_phone' => $settings->company_phone,
                'company_email' => $settings->company_email,
                'brand_color' => $settings->brand_color ?? '#c05427',
                'page_margin_preset' => in_array($settings->page_margin_preset, self::ALLOWED_MARGIN_PRESETS, true)
                    ? $settings->page_margin_preset
                    : 'normal',
                'section_spacing_preset' => in_array($settings->section_spacing_preset, self::ALLOWED_SECTION_SPACING_PRESETS, true)
                    ? $settings->section_spacing_preset
                    : 'normal',
                'qr_alignment' => in_array($settings->qr_alignment, ['left', 'center', 'right'], true)
                    ? $settings->qr_alignment
                    : 'center',
                'qr_width' => $settings->qr_width ?? 120,
                'qr_height' => $settings->qr_height ?? 120,
                'signature_stamp_layout' => $settings->signature_stamp_layout ?? 'default',
                'custom_signature_stamp_layout' => $this->normalizeCustomSignatureStampLayout(
                    $settings->custom_signature_stamp_layout,
                ),
                'logo_path' => $settings->logo_path,
                'qr_image_path' => $settings->qr_image_path,
                'footer_text' => $settings->footer_text,
                'stamp_path' => $settings->stamp_path,
                'signature_path' => $settings->signature_path,
                'custom_stamp_path' => $settings->custom_stamp_path,
                'custom_signature_path' => $settings->custom_signature_path,
                'module_templates' => $moduleTemplates,
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
            'brand_color' => $validated['brand_color'] ?? '#c05427',
            'page_margin_preset' => in_array($validated['page_margin_preset'] ?? null, self::ALLOWED_MARGIN_PRESETS, true)
                ? $validated['page_margin_preset']
                : 'normal',
            'section_spacing_preset' => in_array($validated['section_spacing_preset'] ?? null, self::ALLOWED_SECTION_SPACING_PRESETS, true)
                ? $validated['section_spacing_preset']
                : 'normal',
            'qr_alignment' => in_array($validated['qr_alignment'] ?? null, ['left', 'center', 'right'], true)
                ? $validated['qr_alignment']
                : 'center',
            'qr_width' => $validated['qr_width'] ?? 120,
            'qr_height' => $validated['qr_height'] ?? 120,
            'signature_stamp_layout' => $validated['signature_stamp_layout'] ?? 'default',
            'custom_signature_stamp_layout' => $this->normalizeCustomSignatureStampLayout(
                $validated['custom_signature_stamp_layout'] ?? null,
            ),
            'footer_text' => $validated['footer_text'] ?? null,
            'updated_by' => Auth::id(),
        ]);

        // Update per-module template settings
        if (isset($validated['module_templates'])) {
            $existing = $settings->module_templates ?? [];
            $incoming = $validated['module_templates'];

            // Deep-merge: keep existing keys, overwrite with incoming
            foreach ($incoming as $moduleType => $moduleConfig) {
                // Ensure boolean values are properly cast from string to boolean
                // (FormData sends booleans as strings "true"/"false")
                if (isset($moduleConfig['show_stamp'])) {
                    $moduleConfig['show_stamp'] = filter_var($moduleConfig['show_stamp'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
                }
                if (isset($moduleConfig['show_signature'])) {
                    $moduleConfig['show_signature'] = filter_var($moduleConfig['show_signature'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
                }
                if (isset($moduleConfig['show_qr'])) {
                    $moduleConfig['show_qr'] = filter_var($moduleConfig['show_qr'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
                }
                if (isset($moduleConfig['show_signature_stamp_name'])) {
                    $moduleConfig['show_signature_stamp_name'] = filter_var($moduleConfig['show_signature_stamp_name'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
                }
                if (isset($moduleConfig['show_signature_stamp_date'])) {
                    $moduleConfig['show_signature_stamp_date'] = filter_var($moduleConfig['show_signature_stamp_date'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
                }

                $existing[$moduleType] = array_merge(
                    $existing[$moduleType] ?? ReportSetting::$moduleDefaults[$moduleType] ?? [],
                    $moduleConfig
                );
            }

            $settings->update(['module_templates' => $existing]);
        }

        // Process file uploads - ONLY for files that were actually changed by user
        // Only process keys where file is actually present (not null from form load)
        // Also include path keys with empty string signals for deletion by end-user
        $filteredData = [];

        // Include file uploads
        foreach (array_keys(self::FILE_KEY_MAP) as $fileKey) {
            if (array_key_exists($fileKey, $validated) &&
                ($validated[$fileKey] instanceof \Illuminate\Http\UploadedFile || $validated[$fileKey] === '')) {
                $filteredData[$fileKey] = $validated[$fileKey];
            }
        }

        // Include path deletion signals (when path is empty string)
        foreach (array_values(self::FILE_KEY_MAP) as $pathField) {
            if (array_key_exists($pathField, $validated) && $validated[$pathField] === '') {
                $filteredData[$pathField] = '';
            }
        }

        if (! empty($filteredData)) {
            $this->fileUploadService->processUploads(
                model: $settings,
                data: $filteredData,
                fileKeyMap: self::FILE_KEY_MAP,
                baseDirectory: 'report',
                entityName: 'report-branding',
            );
        }

        $hasUploadedCustomSignature =
            isset($validated['custom_signature_file']) &&
            $validated['custom_signature_file'] instanceof UploadedFile;

        if (! $hasUploadedCustomSignature && ! empty($validated['custom_signature_data'])) {
            $this->persistCustomSignatureFromDataUri(
                reportSetting: $settings,
                dataUri: $validated['custom_signature_data'],
            );
        }

        return back()->with('success', 'Report template settings updated successfully.');
    }

    /**
     * Persist a drawn signature represented as a data URI.
     */
    private function persistCustomSignatureFromDataUri(ReportSetting $reportSetting, string $dataUri): void
    {
        if (! preg_match('/^data:image\/(png|jpeg);base64,/', $dataUri, $matches)) {
            return;
        }

        $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
        $base64Payload = preg_replace('/^data:image\/(png|jpeg);base64,/', '', $dataUri);

        if ($base64Payload === null) {
            return;
        }

        $decoded = base64_decode(str_replace(' ', '+', $base64Payload), true);
        if ($decoded === false) {
            return;
        }

        if (! empty($reportSetting->custom_signature_path) && Storage::disk('public')->exists($reportSetting->custom_signature_path)) {
            Storage::disk('public')->delete($reportSetting->custom_signature_path);
        }

        $fileName = Str::slug($reportSetting->company_name ?: 'report-branding').'-custom-signature-'.now()->timestamp.'.'.$extension;
        $filePath = 'report/'.$fileName;
        Storage::disk('public')->put($filePath, $decoded);

        $reportSetting->update([
            'custom_signature_path' => $filePath,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $layout
     * @return array<string, mixed>|null
     */
    private function normalizeCustomSignatureStampLayout(?array $layout): ?array
    {
        if ($layout === null) {
            return null;
        }

        $unit = ($layout['unit'] ?? 'percent') === 'px' ? 'px' : 'percent';

        $placement = in_array($layout['placement'] ?? null, self::ALLOWED_PLACEMENTS, true)
            ? $layout['placement']
            : 'left_side';

        $preset = self::PRESET_LAYOUTS[$unit][$placement] ?? self::PRESET_LAYOUTS['percent']['left_side'];

        $labels = $layout['labels'] ?? [];
        $legacyStamp = $layout['stamp'] ?? [];
        $legacySignature = $layout['signature'] ?? [];

        $layout['unit'] = $unit;
        $layout['placement'] = $placement;
        $layout['stamp'] = $preset['stamp'];
        $layout['signature'] = $preset['signature'];
        $layout['labels'] = [
            'show_name' => (bool) ($labels['show_name'] ?? false),
            'show_date' => (bool) ($labels['show_date'] ?? false),
            'full_name' => (string) (
                $labels['full_name'] ??
                ($labels['signature_name'] ??
                    ($labels['stamp_name'] ??
                        ($legacySignature['name'] ??
                            ($legacyStamp['name'] ?? ''))))
            ),
            'date' => $this->normalizeLayoutDate(
                $labels['date'] ?? ($legacySignature['date'] ?? null),
            ),
        ];

        return $layout;
    }

    private function normalizeLayoutDate(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return trim($value);
        }
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
            ->reject(fn ($m) => $m['key'] === $key)
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

    /**
     * Get branding data as JSON (for API/AJAX requests from frontend previews).
     */
    public function getBrandingData()
    {
        return response()->json($this->reportTemplateService->getBranding());
    }

    /**
     * Render a report Blade template as HTML with current (unsaved) settings.
     * Used for live preview on the Report Template settings page.
     */
    public function preview(Request $request)
    {
        $moduleKey = $request->input('module_key', 'quotation');

        // Map of blade view: module key -> view name
        $viewMap = [
            'quotation' => 'quotations.report-content',
            'invoice' => 'invoices.report-content',
            'receipt' => 'receipts.report-content',
            'sales' => 'sales.report-content',
            'package' => 'packages.report-content',
            'manifest_arabic_names' => 'manifests.arabic-names-report-content',
            'manifest_airline_names' => 'manifests.airline-names-report-content',
            'manifest_namelist_course_items' => 'manifests.namelist-course-items-report-content',
            'manifest_room_check' => 'manifests.room-check-report-content',
            'ops_movement' => 'ops-movements.report-content',
            'ops_movement_pif' => 'ops-movements.pif-report-content',
            'ops_movement_budget' => 'ops-movements.budget-report-content',
            'payment_summary' => 'reports.dashboard-payment-summary',
            'customer_receipts' => 'customer-confirmations.member-receipts-report',
        ];

        $viewName = $viewMap[$moduleKey] ?? 'quotations.report-content';

        // Build branding from request data (current unsaved form state)
        $brandColor = $request->input('brand_color', '#c05427');
        $signatureStampLayout = $request->input('signature_stamp_layout', 'default');
        $customLayout = $request->input('custom_signature_stamp_layout');

        // Resolve logos from saved DB paths (not re-uploaded within preview)
        $settings = ReportSetting::current();
        $logo = $settings->logo_path ? [
            'url' => '/storage/'.$settings->logo_path,
            'absolute' => storage_path('app/public/'.$settings->logo_path),
        ] : ['url' => '/logo-primary.png', 'absolute' => null];

        $stamp = $settings->stamp_path ? [
            'url' => '/storage/'.$settings->stamp_path,
            'absolute' => storage_path('app/public/'.$settings->stamp_path),
        ] : ['url' => null, 'absolute' => null];

        $signature = $settings->signature_path ? [
            'url' => '/storage/'.$settings->signature_path,
            'absolute' => storage_path('app/public/'.$settings->signature_path),
        ] : ['url' => null, 'absolute' => null];

        $qrImage = $settings->qr_image_path ? [
            'url' => '/storage/'.$settings->qr_image_path,
            'absolute' => storage_path('app/public/'.$settings->qr_image_path),
        ] : ['url' => null, 'absolute' => null];

        $customStamp = $settings->custom_stamp_path ? [
            'url' => '/storage/'.$settings->custom_stamp_path,
            'absolute' => storage_path('app/public/'.$settings->custom_stamp_path),
        ] : ['url' => null, 'absolute' => null];

        $customSignature = $settings->custom_signature_path ? [
            'url' => '/storage/'.$settings->custom_signature_path,
            'absolute' => storage_path('app/public/'.$settings->custom_signature_path),
        ] : ['url' => null, 'absolute' => null];

        // Build module template settings from request
        $moduleTemplates = $request->input('module_templates', []);
        $moduleConfig = $moduleTemplates[$moduleKey] ?? [];

        $branding = [
            'company_name' => $request->input('company_name', $settings->company_name),
            'company_address' => $request->input('company_address', $settings->company_address),
            'company_phone' => $request->input('company_phone', $settings->company_phone),
            'company_email' => $request->input('company_email', $settings->company_email),
            'title_color' => $brandColor,
            'page_margin_preset' => in_array((string) $request->input('page_margin_preset', $settings->page_margin_preset), self::ALLOWED_MARGIN_PRESETS, true)
                ? (string) $request->input('page_margin_preset', $settings->page_margin_preset)
                : 'normal',
            'section_spacing_preset' => in_array((string) $request->input('section_spacing_preset', $settings->section_spacing_preset), self::ALLOWED_SECTION_SPACING_PRESETS, true)
                ? (string) $request->input('section_spacing_preset', $settings->section_spacing_preset)
                : 'normal',
            'signature_stamp_layout' => $signatureStampLayout,
            'qr_alignment' => in_array((string) $request->input('qr_alignment', $settings->qr_alignment), ['left', 'center', 'right'], true)
                ? (string) $request->input('qr_alignment', $settings->qr_alignment)
                : 'center',
            'qr_width' => (int) $request->input('qr_width', $settings->qr_width ?? 120),
            'qr_height' => (int) $request->input('qr_height', $settings->qr_height ?? 120),
            'custom_signature_stamp_layout' => is_array($customLayout) ? $customLayout : ($settings->custom_signature_stamp_layout ?? []),
            'logo_url' => $logo['url'],
            'qr_url' => $qrImage['url'],
            'stamp_url' => $stamp['url'],
            'signature_url' => $signature['url'],
            'custom_stamp_url' => $customStamp['url'],
            'custom_signature_url' => $customSignature['url'],
            'logo_path_absolute' => $logo['absolute'],
            'qr_path_absolute' => $qrImage['absolute'],
            'stamp_path_absolute' => $stamp['absolute'],
            'signature_path_absolute' => $signature['absolute'],
            'custom_stamp_path_absolute' => $customStamp['absolute'],
            'custom_signature_path_absolute' => $customSignature['absolute'],
            'footer_text' => $moduleConfig['footer_text'] ?? $settings->footer_text ?? '',
            'show_stamp' => (bool) ($moduleConfig['show_stamp'] ?? false),
            'show_signature' => (bool) ($moduleConfig['show_signature'] ?? false),
            'show_qr' => (bool) ($moduleConfig['show_qr'] ?? true),
            'show_signature_stamp_name' => (bool) ($moduleConfig['show_signature_stamp_name'] ?? false),
            'show_signature_stamp_date' => (bool) ($moduleConfig['show_signature_stamp_date'] ?? false),
        ];

        // Dummy data for each template type (safe, minimal)
        $data = [
            // shared
            'customer_name' => 'Sample Customer',
            'customer_address' => '123 Sample Street, Singapore 123456',
            'customer_contact' => '91234567',
            'customer_email' => 'sample@example.com',
            'customer_number' => 'CUST-001',
            'description' => 'Sample Service Description',
            // quotation
            'quotation_number' => 'QUO-2025-0001',
            'payment_plan_label' => 'Full Payment',
            'sales_registration_number' => null,
            // invoice
            'invoice_number' => 'INV-2025-0001',
            'order_number' => 'ORD-2025-0001',
            'invoice_date' => date('d/m/Y'),
            'due_date' => null,
            // receipt
            'receipt_number' => 'RCT-2025-0001',
            'payment_date' => date('d/m/Y'),
            'payment_method' => 'Bank Transfer',
            // sales
            'sales_number' => 'SAL-2025-0001',
            'consultant' => 'Sample Consultant',
            // package
            'package_code' => 'PKG-0001',
            'package_name' => 'Sample Package Tour',
            'tour_start' => date('d/m/Y'),
            'tour_end' => date('d/m/Y', strtotime('+7 days')),
            'pax' => 2,
            // totals
            'subtotal_amount' => 1500.00,
            'total_amount' => 1500.00,
            'extension_total_amount' => 0,
            'extensions' => [],
            'invoice_payment_progress' => [
                [
                    'label' => '1st Payment',
                    'amount_paid' => 500.00,
                    'total_amount' => 1500.00,
                ],
                [
                    'label' => '2nd Payment',
                    'amount_paid' => 1000.00,
                    'total_amount' => 1500.00,
                ],
            ],
            'notes' => [],
        ];

        $items = [
            [
                'id' => 1,
                'parent_id' => null,
                'parent_key' => null,
                '_key' => 'item-1',
                'description' => 'Sample Item Description',
                'rate' => 1500.00,
                'quantity' => 1,
                'sort_order' => 1,
                'is_header' => false,
            ],
        ];

        $manifest = [
            'manifest_number' => 'MNF-2025-0001',
            'package_name' => 'Sample Umrah Package',
            'package_number' => 'PKG-0001',
            'departure_date' => date('d/m/Y'),
            'return_date' => date('d/m/Y', strtotime('+12 days')),
            'in_charge_official_name' => 'Sample Official',
            'in_charge_official_contact_number' => '+60123456789',
            'members' => [
                ['name_as_per_passport' => 'Ahmad Bin Abu', 'arabic_name' => 'أحمد بن أبو', 'status' => 'confirmed'],
                ['name_as_per_passport' => 'Siti Binti Ali', 'arabic_name' => 'سيتي بنت علي', 'status' => 'confirmed'],
            ],
            'package_accommodations' => [
                ['location' => 'Makkah', 'hotel_name' => 'Sample Hotel Makkah', 'check_in_formatted' => date('d/m/Y', strtotime('+1 days'))],
                ['location' => 'Madinah', 'hotel_name' => 'Sample Hotel Madinah', 'check_in_formatted' => date('d/m/Y', strtotime('+6 days'))],
            ],
            'room_check_location_label' => 'Makkah',
            'room_check_rows' => [
                [
                    'sharing_group_key' => 'group-1',
                    'room_label' => 'Room 1',
                    'room_number' => '101',
                    'room_type' => 'Double',
                    'bed_type' => 'Single',
                    'name_as_per_passport' => 'Ahmad Bin Abu',
                    'room_relationship' => 'Husband',
                    'passport_number' => 'A12345678',
                    'date_of_birth' => '01/01/1980',
                    'age' => 45,
                    'meal' => 'Full Board',
                    'status' => 'confirmed',
                ],
                [
                    'sharing_group_key' => 'group-1',
                    'name_as_per_passport' => 'Siti Binti Ali',
                    'passport_number' => 'A87654321',
                    'date_of_birth' => '01/01/1985',
                    'age' => 40,
                    'status' => 'confirmed',
                ],
            ],
        ];

        $opsMovement = [
            'package_number' => 'PKG-OPS-0001',
            'manifest_number' => 'MNF-OPS-0001',
            'ops_movement_number' => 'KTG01-26',
            'name' => 'Sample Ops Movement Package',
            'departure_return_range' => date('d/m/Y').' - '.date('d/m/Y', strtotime('+10 days')),
            'visa_type' => 'Umrah',
            'first_hotel_name' => 'Sample Hotel',
            'ops_base' => 'Makkah Desk',
            'infotech_ref' => 'INFO-1234',
            'location' => 'Jeddah',
            'doa_by' => 'Ustadz Fulan',
            'doa_datetime' => date('d/m/Y H:i', strtotime('+1 days 06:30')),
            'vehicle_type' => 'Bus',
            'vehicle_driver_name' => 'Sample Driver',
            'vehicle_driver_contact_number' => '+60123456789',
            'train_description' => 'Sample train movement notes',
            'passengers' => [
                'adult_total' => 20,
                'adult_male' => 11,
                'adult_female' => 9,
                'child_total' => 2,
                'child_boy' => 1,
                'child_girl' => 1,
                'official_total' => 2,
                'wheelchair_non_official_total' => 1,
                'grand_total' => 24,
            ],
            'passenger_details' => [
                [
                    'name' => 'Ahmad Bin Abu',
                    'role' => 'passenger',
                    'passport_number' => 'A12345678',
                    'gender' => 'male',
                    'age' => 45,
                ],
                [
                    'name' => 'Siti Binti Ali',
                    'role' => 'passenger',
                    'passport_number' => 'A87654321',
                    'gender' => 'female',
                    'age' => 40,
                ],
            ],
            'pif' => [
                'tour_leaders' => [
                    ['type' => 'saudi', 'name' => 'TL Saudi', 'contact_number' => '+9665000001'],
                    ['type' => 'singapore', 'name' => 'TL Singapore', 'contact_number' => '+6591000002'],
                ],
            ],
            'accommodations' => [
                [
                    'location' => 'Makkah',
                    'hotel_name' => 'Sample Makkah Hotel',
                    'check_in' => date('d/m/Y', strtotime('+1 days')),
                    'check_out' => date('d/m/Y', strtotime('+5 days')),
                    'type_of_meal' => 'Full Board',
                    'ic' => 'IC-001',
                    'room_counts' => ['single' => 2, 'double' => 4, 'triple' => 1, 'quad' => 0],
                ],
            ],
            'officials' => [
                [
                    'name' => 'Official A',
                    'hotel' => 'Sample Makkah Hotel',
                    'hotels_by_location' => [
                        ['location' => 'Makkah', 'hotel' => 'Sample Makkah Hotel'],
                        ['location' => 'Madinah', 'hotel' => 'Sample Madinah Hotel'],
                    ],
                ],
            ],
            'flights' => [
                [
                    'description' => 'Departure',
                    'from' => 'KUL',
                    'to' => 'JED',
                    'departure_datetime' => date('d/m/Y H:i', strtotime('+1 days 08:00')),
                    'arrival_datetime' => date('d/m/Y H:i', strtotime('+1 days 14:00')),
                    'airline' => 'SV',
                    'pnr' => 'SV123',
                    'doa_by' => 'Amir',
                    'doa_datetime' => date('d/m/Y H:i', strtotime('+1 days 06:30')),
                    'ic' => 'IC-FLT-001',
                ],
            ],
            'transportation_plans' => [
                [
                    'from' => 'Airport',
                    'to' => 'Hotel',
                    'travel_date' => date('d/m/Y', strtotime('+1 days')),
                    'travel_time' => '15:30',
                    'remarks' => 'Arrival transfer',
                ],
            ],
            'rawdah_tasreehs' => [
                [
                    'date' => date('d/m/Y', strtotime('+6 days')),
                    'women_passengers' => 10,
                    'women_time' => '08:00',
                    'men_passengers' => 11,
                    'men_time' => '10:00',
                    'remarks' => 'Group slot A',
                ],
            ],
            'budget' => [
                [
                    'title' => 'Transportation',
                    'items' => [
                        [
                            'item_name' => 'Bus transfer',
                            'unit_price' => 500,
                            'quantity' => 2,
                            'remarks' => 'Airport to hotel',
                        ],
                    ],
                ],
            ],
        ];

        $report = [
            'mode' => 'daily',
            'period_label' => 'Daily',
            'generated_at' => now()->translatedFormat('d M Y, H:i'),
            'generated_by' => 'System',
            'groups' => [
                [
                    'label' => now()->translatedFormat('d M Y'),
                    'day_name' => now()->translatedFormat('l'),
                    'rows' => [
                        [
                            'category' => 'umrah_packages',
                            'package_item' => 'Sample Umrah Package',
                            'ref_no' => 'INV-2025-0001',
                            'amount' => 1500.00,
                            'cash' => 0.00,
                            'nets' => 0.00,
                            'visa' => 0.00,
                            'master' => 0.00,
                            'paynow' => 1500.00,
                            'total_sale' => 1500.00,
                            'maker' => '-',
                            'remarks' => '-',
                        ],
                    ],
                ],
            ],
        ];

        try {
            $html = view($viewName, [
                'branding' => $branding,
                'data' => $data,
                'items' => $items,
                'manifest' => $manifest,
                'opsMovement' => $opsMovement,
                'report' => $report,
                'is_pdf' => false,
            ])->render();

            return response()->json(['html' => $html]);
        } catch (\Throwable $e) {
            // Fallback to quotation view if module-specific view fails
            try {
                $html = view('quotations.report-content', [
                    'branding' => $branding,
                    'data' => $data,
                    'items' => $items,
                    'is_pdf' => false,
                ])->render();

                return response()->json(['html' => $html]);
            } catch (\Throwable $e2) {
                return response()->json(['html' => '<p style="color:red;padding:12px;">Preview unavailable: '.e($e->getMessage()).'</p>']);
            }
        }
    }
}
