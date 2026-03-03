<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Rules\BranchRule;
use App\Services\BranchService;
use App\Services\CountryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BranchController extends Controller
{
    protected $branchService;

    protected $countryService;

    protected $branchRule;

    public function __construct(BranchService $branchService, CountryService $countryService, BranchRule $branchRule)
    {
        $this->branchService = $branchService;
        $this->countryService = $countryService;
        $this->branchRule = $branchRule;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $dataBranch = $this->branchService->getForDataTable();
        $dataCountry = $this->countryService->getForFilter();

        return Inertia::render('masters/branch/index', [
            'dataBranch' => $dataBranch,
            'dataCountry' => $dataCountry,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $dataCountry = $this->countryService->getForFilter();

        return Inertia::render('masters/branch/create', [
            'dataCountry' => $dataCountry,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->branchRule->rules());

        $this->branchService->store($validated);

        return redirect()->intended(route('master.branch.index'))->with('success', 'Branch created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data = $this->branchService->getForEditShow($id);
        $dataCountry = $this->countryService->getForFilter();

        return Inertia::render('masters/branch/view', [
            'data' => $data,
            'dataCountry' => $dataCountry,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = $this->branchService->getForEditShow($id);
        $dataCountry = $this->countryService->getForFilter();

        return Inertia::render('masters/branch/edit', [
            'data' => $data,
            'dataCountry' => $dataCountry,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate($this->branchRule->rules());

        $this->branchService->update($validated, $id);

        return redirect()->intended(route('master.branch.index'))->with('success', 'Branch updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $userId) {
                $this->branchService->delete($userId);
            }

            return redirect()->intended(route('master.branch.index'))->with('success', 'Selected branchs deleted successfully.');
        }

        $this->branchService->delete($id);

        return redirect()->intended(route('master.branch.index'))->with('success', 'Branch deleted successfully.');
    }
}
