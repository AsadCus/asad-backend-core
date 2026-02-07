<?php

namespace App\Http\Controllers;

use App\Rules\ManifestRule;
use App\Services\ManifestService;
use App\Services\PackageService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ManifestController extends Controller
{
    protected $manifestService;
    protected $manifestRule;
    protected $packageService;

    public function __construct(ManifestService $manifestService, ManifestRule $manifestRule, PackageService $packageService)
    {
        $this->manifestService = $manifestService;
        $this->manifestRule = $manifestRule;
        $this->packageService = $packageService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data['manifestsForDatatable'] = $this->manifestService->getForDataTable();

        return Inertia::render('manifests/index', [
            'data' => $data,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $dataPackage = $this->packageService->getForFilter();

        return Inertia::render('manifests/create', [
            'dataPackage' => $dataPackage,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->manifestRule->rules());
        $this->manifestService->store($validated);

        return redirect()->route('manifests.index')
            ->with('success', 'Manifest created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $manifest = $this->manifestService->getForEditShow($id);
        $dataPackage = $this->packageService->getForFilter();

        return Inertia::render('manifests/show', [
            'data' => $manifest,
            'dataPackage' => $dataPackage,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $manifest = $this->manifestService->getForEditShow($id);
        $dataPackage = $this->packageService->getForFilter();

        return Inertia::render('manifests/edit', [
            'data' => $manifest,
            'dataPackage' => $dataPackage,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate($this->manifestRule->rules($id));
        $this->manifestService->update($validated, $id);

        return redirect()->route('manifests.index')
            ->with('success', 'Manifest updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $ids = $request->input('ids');
        if ($ids && is_array($ids)) {
            foreach ($ids as $manifestId) {
                $this->manifestService->delete($manifestId);
            }

            return redirect()->route('manifests.index')
                ->with('success', 'Selected manifests deleted successfully.');
        }

        $this->manifestService->delete($id);

        return redirect()->route('manifests.index')
            ->with('success', 'Manifest deleted successfully.');
    }
}
