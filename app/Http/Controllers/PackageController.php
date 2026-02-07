<?php

namespace App\Http\Controllers;

use App\Rules\PackageRule;
use App\Services\PackageService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PackageController extends Controller
{
    protected $packageService;
    protected $packageRule;

    public function __construct(PackageService $packageService, PackageRule $packageRule)
    {
        $this->packageService = $packageService;
        $this->packageRule = $packageRule;
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
        return Inertia::render('packages/create');
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
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $package = $this->packageService->getForEditShow($id);

        return Inertia::render('packages/edit', [
            'data' => $package,
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
}
