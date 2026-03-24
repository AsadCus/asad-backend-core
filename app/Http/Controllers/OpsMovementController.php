<?php

namespace App\Http\Controllers;

use App\Rules\OpsMovementRule;
use App\Services\OpsMovementService;
use App\Services\Report\ReportTemplateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class OpsMovementController extends Controller
{
    protected $opsMovementService;

    protected OpsMovementRule $opsMovementRule;

    protected ReportTemplateService $reportTemplateService;

    public function __construct(
        OpsMovementService $opsMovementService,
        OpsMovementRule $opsMovementRule,
        ReportTemplateService $reportTemplateService,
    ) {
        $this->opsMovementService = $opsMovementService;
        $this->opsMovementRule = $opsMovementRule;
        $this->reportTemplateService = $reportTemplateService;
    }

    /**
     * Display a listing of ops movements (read-only view from packages + manifests).
     */
    public function index()
    {
        $data['opsMovementsForDatatable'] = $this->opsMovementService->getForDataTable();

        return Inertia::render('ops-movements/index', [
            'data' => $data,
        ]);
    }

    /**
     * Display the specified ops movement detail (read-only).
     */
    public function show(string $id)
    {
        $opsMovement = $this->opsMovementService->getForShow($id);

        return Inertia::render('ops-movements/show', [
            'data' => $opsMovement,
        ]);
    }

    /**
     * Update editable ops movement fields.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate($this->opsMovementRule->rules());
        $this->opsMovementService->update((int) $id, $validated);

        return redirect()->route('ops-movements.show', $id)
            ->with('success', 'Ops movement updated successfully.');
    }

    public function exportPdf(string $id)
    {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(60);

            $opsMovement = $this->opsMovementService->getForShow((int) $id);
            $reportData = $this->reportTemplateService->build('ops_movement', [
                'ops_movement' => $opsMovement,
            ]);

            $html = view('ops-movements.report-content', [
                'opsMovement' => $opsMovement,
                'branding' => $reportData['branding'],
                'is_pdf' => true,
            ])->render();

            $packageNumber = trim((string) ($opsMovement['package_number'] ?? $id));
            $fileName = 'Ops Movement - '.$packageNumber.'.pdf';

            return Pdf::loadHTML($html)
                ->setPaper('a4', 'landscape')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('dpi', 96)
                ->stream($fileName);
        } catch (\Throwable $e) {
            Log::error('Ops movement PDF generation error', [
                'ops_movement_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to generate PDF: '.$e->getMessage(),
            ], 500);
        }
    }

    public function exportPifPdf(string $id)
    {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(60);

            $opsMovement = $this->opsMovementService->getForShow((int) $id);
            $reportData = $this->reportTemplateService->build('ops_movement_pif', [
                'ops_movement' => $opsMovement,
            ]);

            $html = view('ops-movements.pif-report-content', [
                'opsMovement' => $opsMovement,
                'branding' => $reportData['branding'],
                'is_pdf' => true,
            ])->render();

            $packageNumber = trim((string) ($opsMovement['package_number'] ?? $id));
            $fileName = 'Ops Movement PIF - '.$packageNumber.'.pdf';

            return Pdf::loadHTML($html)
                ->setPaper('a4', 'portrait')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('dpi', 96)
                ->stream($fileName);
        } catch (\Throwable $e) {
            Log::error('Ops movement PIF PDF generation error', [
                'ops_movement_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to generate PDF: '.$e->getMessage(),
            ], 500);
        }
    }

    public function exportBudgetPdf(string $id)
    {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(60);

            $opsMovement = $this->opsMovementService->getForShow((int) $id);
            $reportData = $this->reportTemplateService->build('ops_movement_budget', [
                'ops_movement' => $opsMovement,
            ]);

            $html = view('ops-movements.budget-report-content', [
                'opsMovement' => $opsMovement,
                'branding' => $reportData['branding'],
                'is_pdf' => true,
            ])->render();

            $packageNumber = trim((string) ($opsMovement['package_number'] ?? $id));
            $fileName = 'Ops Movement Budget - '.$packageNumber.'.pdf';

            return Pdf::loadHTML($html)
                ->setPaper('a4', 'landscape')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('dpi', 96)
                ->stream($fileName);
        } catch (\Throwable $e) {
            Log::error('Ops movement budget PDF generation error', [
                'ops_movement_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to generate PDF: '.$e->getMessage(),
            ], 500);
        }
    }
}
