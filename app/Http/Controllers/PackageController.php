<?php

namespace App\Http\Controllers;

use App\Rules\PackageRule;
use App\Services\CountryService;
use App\Services\PackageService;
use App\Services\Report\ReportTemplateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PackageController extends Controller
{
    protected $packageService;

    protected $packageRule;

    protected $countryService;

    protected $reportTemplateService;

    public function __construct(PackageService $packageService, PackageRule $packageRule, CountryService $countryService, ReportTemplateService $reportTemplateService)
    {
        $this->packageService = $packageService;
        $this->packageRule = $packageRule;
        $this->countryService = $countryService;
        $this->reportTemplateService = $reportTemplateService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data['packagesForDatatable'] = $this->packageService->getForDataTable();

        return Inertia::render('packages/index', [
            'data' => $data,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('packages/create', [
            'dataCountry' => $this->countryService->getForFilter(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->packageRule->rules());
        $this->packageService->store($validated);

        return redirect()->route('packages.index')
            ->with('success', 'Package created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $package = $this->packageService->getForEditShow($id);

        return Inertia::render('packages/show', [
            'data' => $package,
            'dataCountry' => $this->countryService->getForFilter(),
        ]);
    }

    /**
     * Get package data for linked-info panels (JSON).
     */
    public function getForShow(string $id)
    {
        return response()->json($this->packageService->getForEditShow($id));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $package = $this->packageService->getForEditShow($id);

        return Inertia::render('packages/edit', [
            'data' => $package,
            'dataCountry' => $this->countryService->getForFilter(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate($this->packageRule->rules($id));
        $this->packageService->update($validated, $id);

        return redirect()->route('packages.index')
            ->with('success', 'Package updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $ids = $request->input('ids');
        if ($ids && is_array($ids)) {
            foreach ($ids as $packageId) {
                $this->packageService->delete($packageId);
            }

            return redirect()->route('packages.index')
                ->with('success', 'Selected packages deleted successfully.');
        }

        $this->packageService->delete($id);

        return redirect()->route('packages.index')
            ->with('success', 'Package deleted successfully.');
    }

    /**
     * Generate package details as PDF.
     */
    public function generatePdf(string $id)
    {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(60);

            $package = $this->packageService->getForEditShow($id);
            $branding = $this->reportTemplateService->getBranding();

            $html = view('packages.report-content', [
                'data' => $package,
                'branding' => $branding,
                'is_pdf' => true,
            ])->render();

            $pdf = Pdf::loadHTML($html)
                ->setPaper('a4')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('dpi', 96);

            $fileName = ($package['package_number'] ?? 'package').'.pdf';

            return $pdf->stream($fileName);
        } catch (\Throwable $e) {
            Log::error('Package PDF Generation Error', ['error' => $e]);

            return response()->json(['error' => 'Failed to generate PDF: '.$e->getMessage()], 500);
        }
    }
}
