<?php

namespace App\Http\Controllers;

use App\Rules\GeneralEnquiryRule;
use App\Services\BranchService;
use App\Services\CountryService;
use App\Services\GeneralEnquiryService;
use App\Services\PackageService;
use App\Services\SalesService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class GeneralEnquiryController extends Controller
{
    public function __construct(
        protected GeneralEnquiryService $generalEnquiryService,
        protected GeneralEnquiryRule $generalEnquiryRule,
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
        return Inertia::render('general-enquiries/index', [
            'data' => [
                'enquiriesForDatatable' => $this->generalEnquiryService->getForDataTable(),
                'packageOptions' => $this->packageService->getForFilter(),
                'branchOptions' => $this->branchService->getForFilter(),
                'countryOptions' => $this->countryService->getForFilter(),
                'scopeMode' => $this->scopeMode(),
                'handledByOptions' => $this->salesService->getForFilter(),
            ],
        ]);
    }

    public function publicForm(Request $request)
    {
        $countryOptions = $this->countryService->getForPublicSelector();
        $countrySlug = trim((string) $request->query('country', ''));

        if ($countrySlug === '') {
            return Inertia::render('general-enquiries/public/index', [
                'countryOptions' => $countryOptions,
                'showCountrySelector' => true,
                'selectedCountry' => null,
            ]);
        }

        $selectedCountry = $this->countryService->findBySlug($countrySlug);

        if (! $selectedCountry) {
            return redirect()->route('general-enquiries.public.create');
        }

        return Inertia::render('general-enquiries/public/index', [
            'countryOptions' => $countryOptions,
            'showCountrySelector' => false,
            'selectedCountry' => [
                'id' => (int) $selectedCountry->id,
                'name' => (string) $selectedCountry->name,
                'slug' => strtolower(trim((string) $countrySlug)),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('general-enquiries/create', [
            'packageOptions' => $this->packageService->getForFilter(),
            'branchOptions' => $this->branchService->getForFilter(),
            'countryOptions' => $this->countryService->getForFilter(),
            'scopeMode' => $this->scopeMode(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->generalEnquiryRule->rules());
        $this->ensureInternalScopeProvided($validated);
        $generalEnquiry = $this->generalEnquiryService->store($validated);

        // Update package on the parent enquiry if provided
        if ($request->has('package_id')) {
            $generalEnquiry->enquiry?->update(['package_id' => $request->input('package_id')]);
        }

        return redirect()->route('general-enquiries.index')
            ->with('success', 'General enquiry created successfully.');
    }

    /**
     * Store a public general enquiry (no authentication required).
     */
    public function storePublic(Request $request)
    {
        $selectedCountry = $this->resolvePublicCountryFromRequest($request);

        if (! $selectedCountry) {
            return redirect()->route('general-enquiries.public.create');
        }

        $validated = $request->validate($this->generalEnquiryRule->rules());
        $validated['country_id'] = (int) $selectedCountry->id;
        $validated['branch_id'] = null;
        $this->generalEnquiryService->store($validated);

        return redirect()->route('general-enquiries.public.create', ['country' => \Illuminate\Support\Str::slug((string) $selectedCountry->name)])
            ->with('success', 'Thank you for your enquiry. We will get back to you soon.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $enquiry = $this->generalEnquiryService->getForEditShow($id);

        return Inertia::render('general-enquiries/show', [
            'data' => $enquiry,
        ]);
    }

    /**
     * Get general enquiry data for the show modal (JSON).
     */
    public function getForShow(string $id)
    {
        return response()->json($this->generalEnquiryService->getForEditShow($id));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $enquiry = $this->generalEnquiryService->getForEditShow($id);

        return Inertia::render('general-enquiries/edit', [
            'data' => $enquiry,
            'packageOptions' => $this->packageService->getForFilter(),
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
        $validated = $request->validate($this->generalEnquiryRule->rules($id));
        $this->ensureInternalScopeProvided($validated);
        $this->generalEnquiryService->update($validated, $id);

        // Update package on the parent enquiry if provided
        $generalEnquiry = \App\Models\GeneralEnquiry::findOrFail($id);
        if ($request->has('package_id')) {
            $generalEnquiry->enquiry?->update(['package_id' => $request->input('package_id')]);
        }

        return redirect()->route('general-enquiries.index')
            ->with('success', 'General enquiry updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $ids = $request->input('ids');
        if ($ids && is_array($ids)) {
            foreach ($ids as $userId) {
                $this->generalEnquiryService->delete($userId);
            }

            return redirect()->intended(route('general-enquiries.index'))->with('success', 'Selected general enquiries deleted successfully.');
        }

        $this->generalEnquiryService->delete($id);

        return redirect()->route('general-enquiries.index')
            ->with('success', 'General enquiry deleted successfully.');
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

    private function resolvePublicCountryFromRequest(Request $request): ?\App\Models\Country
    {
        $countrySlug = trim((string) $request->input('country_slug', $request->query('country', '')));

        if ($countrySlug === '') {
            return null;
        }

        return $this->countryService->findBySlug($countrySlug);
    }
}
