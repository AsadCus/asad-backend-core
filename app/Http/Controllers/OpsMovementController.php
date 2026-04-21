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
        ReportTemplateService $reportTemplateService
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

        if (! $request->user()?->hasRole('admin')) {
            unset($validated['budget'], $validated['budget_currency']);
        }

        $this->opsMovementService->update((int) $id, $validated);

        return redirect()->route('ops-movements.show', $id)
            ->with('success', 'Ops movement updated successfully.');
    }

    /**
     * Export ops movement report as PDF.
     */
    public function exportPdf(string $id)
    {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(60);

            $opsMovement = $this->opsMovementService->getForShow((int) $id);

            $reportData = $this->reportTemplateService->build('ops_movement', [
                'opsMovement' => $opsMovement,
            ]);

            $html = view('ops-movements.report-content', [
                'opsMovement' => $opsMovement,
                'branding' => $reportData['branding'],
                'is_pdf' => true,
            ])->render();

            $fileName = 'Ops Movement - '.($opsMovement['ops_movement_number'] ?? $opsMovement['id']).'.pdf';

            return Pdf::loadHTML($html)
                ->setPaper('a4', 'landscape')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('dpi', 96)
                ->stream($fileName);
        } catch (\Throwable $e) {
            Log::error('Ops movement PDF generation error', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to generate PDF: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export ops movement PIF report as PDF.
     */
    public function exportPifPdf(string $id)
    {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(60);

            $opsMovement = $this->opsMovementService->getForShow((int) $id);

            $reportData = $this->reportTemplateService->build('ops_movement_pif', [
                'opsMovement' => $opsMovement,
            ]);

            $html = view('ops-movements.pif-report-content', [
                'opsMovement' => $opsMovement,
                'branding' => $reportData['branding'],
                'is_pdf' => true,
            ])->render();

            $fileName = 'Ops Movement PIF - '.($opsMovement['ops_movement_number'] ?? $opsMovement['id']).'.pdf';

            return Pdf::loadHTML($html)
                ->setPaper('a4', 'portrait')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('dpi', 96)
                ->stream($fileName);
        } catch (\Throwable $e) {
            Log::error('Ops movement PIF PDF generation error', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to generate PDF: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export ops movement budget report as PDF.
     */
    public function exportBudgetPdf(Request $request, string $id)
    {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(60);

            $opsMovement = $this->opsMovementService->getForShow((int) $id);

            $budgetSnapshotJson = $request->input('budget_snapshot');

            if (is_string($budgetSnapshotJson) && trim($budgetSnapshotJson) !== '') {
                $decodedSnapshot = json_decode($budgetSnapshotJson, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedSnapshot)) {
                    if (array_key_exists('budget', $decodedSnapshot)) {
                        $opsMovement['budget'] = $this->opsMovementService->normalizeBudgetForReport($decodedSnapshot['budget']);
                    }

                    if (array_key_exists('budget_currency', $decodedSnapshot)) {
                        $normalizedBudgetCurrency = trim((string) $decodedSnapshot['budget_currency']);
                        $opsMovement['budget_currency'] = $normalizedBudgetCurrency !== ''
                            ? $normalizedBudgetCurrency
                            : null;
                    }
                }
            }

            $reportData = $this->reportTemplateService->build('ops_movement_budget', [
                'opsMovement' => $opsMovement,
            ]);

            $html = view('ops-movements.budget-report-content', [
                'opsMovement' => $opsMovement,
                'branding' => $reportData['branding'],
                'is_pdf' => true,
            ])->render();

            $fileName = 'Ops Movement Budget - '.($opsMovement['ops_movement_number'] ?? $opsMovement['id']).'.pdf';

            return Pdf::loadHTML($html)
                ->setPaper('a4', 'landscape')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('dpi', 96)
                ->stream($fileName);
        } catch (\Throwable $e) {
            Log::error('Ops movement budget PDF generation error', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to generate PDF: '.$e->getMessage(),
            ], 500);
        }
    }
}
