<?php

namespace App\Http\Controllers;

use App\Rules\GeneralEnquiryRule;
use App\Services\GeneralEnquiryService;
use App\Services\PackageService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GeneralEnquiryController extends Controller
{
    public function __construct(
        protected GeneralEnquiryService $generalEnquiryService,
        protected GeneralEnquiryRule $generalEnquiryRule,
        protected PackageService $packageService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data['enquiriesForDatatable'] = $this->generalEnquiryService->getForDataTable();
        $data['packageOptions'] = $this->packageService->getForFilter();

        return Inertia::render('general-enquiries/index', [
            'data' => $data,
        ]);
    }

    public function publicForm()
    {
        return Inertia::render('general-enquiries/public/index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('general-enquiries/create', [
            'packageOptions' => $this->packageService->getForFilter(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->generalEnquiryRule->rules());
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
        $validated = $request->validate($this->generalEnquiryRule->rules());
        $this->generalEnquiryService->store($validated);

        return back()->with('success', 'Thank you for your enquiry. We will get back to you soon.');
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
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate($this->generalEnquiryRule->rules($id));
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
}
