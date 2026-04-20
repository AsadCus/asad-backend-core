<?php

namespace App\Http\Controllers;

use App\Rules\PrivateEnquiryRule;
use App\Services\BranchService;
use App\Services\CountryService;
use App\Services\PackageService;
use App\Services\PrivateEnquiryService;
use App\Services\SalesService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PrivateEnquiryController extends Controller
{
    public function __construct(
        protected PrivateEnquiryService $privateEnquiryService,
        protected PrivateEnquiryRule $privateEnquiryRule,
        protected PackageService $packageService,
        protected BranchService $branchService,
        protected CountryService $countryService,
        protected SalesService $salesService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('private-enquiries/index', [
            'data' => [
                'enquiriesForDatatable' => $this->privateEnquiryService->getForDataTable(),
                'packageOptions' => $this->packageService->getForFilter(),
                'branchOptions' => $this->branchService->getForFilter(),
                'countryOptions' => $this->countryService->getForFilter(),
                'scopeMode' => $this->scopeMode(),
                'handledByOptions' => $this->salesService->getForFilter(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('private-enquiries/create', [
            'branchOptions' => $this->branchService->getForFilter(),
            'countryOptions' => $this->countryService->getForFilter(),
            'scopeMode' => $this->scopeMode(),
        ]);
    }

    /**
     * Show the public form for creating a new private enquiry.
     */
    public function publicForm()
    {
        return Inertia::render('private-enquiries/public/index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->privateEnquiryRule->rules());
        $this->ensureInternalScopeProvided($validated);
        $this->privateEnquiryService->store($validated);

        return redirect()->route('private-enquiries.index')
            ->with('success', 'Private enquiry created successfully.');
    }

    /**
     * Store a public private enquiry (no authentication required).
     */
    public function storePublic(Request $request)
    {
        $validated = $request->validate($this->privateEnquiryRule->rules());
        $this->privateEnquiryService->store($validated);

        return back()->with('success', 'Thank you for your enquiry. We will get back to you soon with a detailed quotation.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $enquiry = $this->privateEnquiryService->getForEditShow($id);

        return Inertia::render('private-enquiries/show', [
            'data' => $enquiry,
        ]);
    }

    /**
     * Get private enquiry data for the show modal (JSON).
     */
    public function getForShow(string $id)
    {
        return response()->json($this->privateEnquiryService->getForEditShow($id));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $enquiry = $this->privateEnquiryService->getForEditShow($id);

        return Inertia::render('private-enquiries/edit', [
            'data' => $enquiry,
            'branchOptions' => $this->branchService->getForFilter(),
            'countryOptions' => $this->countryService->getForFilter(),
            'scopeMode' => $this->scopeMode(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate($this->privateEnquiryRule->rules($id));
        $this->ensureInternalScopeProvided($validated);

        $this->privateEnquiryService->update($validated, $id);

        return redirect()->route('private-enquiries.index')
            ->with('success', 'Private enquiry updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $ids = $request->input('ids');
        if ($ids && is_array($ids)) {
            foreach ($ids as $enquiryId) {
                $this->privateEnquiryService->delete($enquiryId);
            }

            return redirect()->intended(route('private-enquiries.index'))->with('success', 'Selected private enquiries deleted successfully.');
        }

        $this->privateEnquiryService->delete($id);

        return redirect()->route('private-enquiries.index')
            ->with('success', 'Private enquiry deleted successfully.');
    }

    /**
     * @throws ValidationException
     */
    private function ensureInternalScopeProvided(array $validated): void
    {
        $scopeMode = $this->scopeMode();

        if ($scopeMode === 'branch' && empty($validated['branch_id'])) {
            throw ValidationException::withMessages([
                'branch_id' => 'Branch is required.',
            ]);
        }

        if ($scopeMode === 'country' && empty($validated['country_id'])) {
            throw ValidationException::withMessages([
                'country_id' => 'Country is required.',
            ]);
        }
    }

    private function scopeMode(): string
    {
        return strtolower((string) config('data_scope.mode', 'country'));
    }
}
