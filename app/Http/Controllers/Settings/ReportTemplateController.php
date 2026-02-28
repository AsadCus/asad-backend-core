<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ReportTemplateUpdateRequest;
use App\Models\ReportSetting;
use App\Services\Report\ReportTemplateService;
use App\Services\UserRoleFileUploadService;
use Illuminate\Http\RedirectResponse;
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
    ) {}

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
}
