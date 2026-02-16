<?php

namespace App\Http\Controllers;

use App\Rules\EnquiryRemarkRule;
use App\Services\EnquiryRemarkService;
use Illuminate\Http\Request;

class EnquiryRemarkController extends Controller
{
    public function __construct(
        public EnquiryRemarkService $enquiryRemarkService,
        public EnquiryRemarkRule $enquiryRemarkRule,
    ) {}

    /**
     * Get all remarks for an enquiry (JSON).
     */
    public function index(int $enquiryId)
    {
        return response()->json($this->enquiryRemarkService->getForEnquiry($enquiryId));
    }

    /**
     * Store a new remark for an enquiry.
     */
    public function store(Request $request, int $enquiryId)
    {
        $validated = $request->validate($this->enquiryRemarkRule->rules());
        $this->enquiryRemarkService->store($enquiryId, $validated);

        return back()->with('success', 'Remark added successfully.');
    }

    /**
     * Update an existing remark.
     */
    public function update(Request $request, int $enquiryId, int $remarkId)
    {
        $validated = $request->validate($this->enquiryRemarkRule->updateRules());
        $this->enquiryRemarkService->update($remarkId, $validated);

        return back()->with('success', 'Remark updated successfully.');
    }

    /**
     * Delete a remark.
     */
    public function destroy(int $enquiryId, int $remarkId)
    {
        $this->enquiryRemarkService->delete($remarkId);

        return back()->with('success', 'Remark deleted successfully.');
    }
}
