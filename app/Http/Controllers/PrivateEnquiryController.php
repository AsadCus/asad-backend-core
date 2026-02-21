<?php

namespace App\Http\Controllers;

use App\Rules\PrivateEnquiryRule;
use App\Services\PackageService;
use App\Services\PrivateEnquiryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PrivateEnquiryController extends Controller
{
    public function __construct(
        protected PrivateEnquiryService $privateEnquiryService,
        protected PrivateEnquiryRule $privateEnquiryRule,
        protected PackageService $packageService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data['enquiriesForDatatable'] = $this->privateEnquiryService->getForDataTable();
        $data['packageOptions'] = $this->packageService->getForFilter();

        return Inertia::render('private-enquiries/index', [
            'data' => $data,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('private-enquiries/create');
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
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate($this->privateEnquiryRule->rules($id));

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
}
