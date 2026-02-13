<?php

namespace App\Http\Controllers;

use App\Rules\GeneralEnquiryRule;
use App\Services\GeneralEnquiryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GeneralEnquiryController extends Controller
{
    protected $generalEnquiries;

    protected $generalEnquiryRule;

    public function __construct(GeneralEnquiryService $generalEnquiries, GeneralEnquiryRule $generalEnquiryRule)
    {
        $this->generalEnquiries = $generalEnquiries;
        $this->generalEnquiryRule = $generalEnquiryRule;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data['enquiriesForDatatable'] = $this->generalEnquiries->getForDataTable();

        return Inertia::render('general-enquiries/index', [
            'data' => $data,
        ]);
    }

    public function publicForm()
    {
        return Inertia::render('general-enquiries/public/form');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('general-enquiries/create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->generalEnquiryRule->rules());
        $this->generalEnquiries->store($validated);

        return redirect()->route('general-enquiries.index')
            ->with('success', 'General enquiry created successfully.');
    }

    /**
     * Store a public general enquiry (no authentication required).
     */
    public function storePublic(Request $request)
    {
        $validated = $request->validate($this->generalEnquiryRule->rules());
        $this->generalEnquiries->store($validated);

        return back()->with('success', 'Thank you for your enquiry. We will get back to you soon.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $enquiry = $this->generalEnquiries->getForEditShow($id);

        return Inertia::render('general-enquiries/show', [
            'data' => $enquiry,
        ]);
    }

    /**
     * Get general enquiry data for the show modal (JSON).
     */
    public function getForShow(string $id)
    {
        return response()->json($this->generalEnquiries->getForEditShow($id));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $enquiry = $this->generalEnquiries->getForEditShow($id);

        return Inertia::render('general-enquiries/edit', [
            'data' => $enquiry,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate($this->generalEnquiryRule->rules($id));
        $this->generalEnquiries->update($validated, $id);

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
                $this->generalEnquiries->delete($userId);
            }

            return redirect()->intended(route('general-enquiries.index'))->with('success', 'Selected general enquiries deleted successfully.');
        }

        $this->generalEnquiries->delete($id);

        return redirect()->route('general-enquiries.index')
            ->with('success', 'General enquiry deleted successfully.');
    }
}
