<?php

namespace App\Http\Controllers;

use App\Rules\CustomerGroupRule;
use App\Services\CustomerGroupService;
use App\Services\EnquiryService;
use App\Services\PackageService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EnquiryController extends Controller
{
    public function __construct(
        protected EnquiryService $enquiryService,
        protected CustomerGroupService $customerGroupService,
        protected CustomerGroupRule $customerGroupRule,
        protected PackageService $packageService,
    ) {}

    /**
     * Display a listing of all enquiries (general + private).
     */
    public function index()
    {
        $data['enquiriesForDatatable'] = $this->enquiryService->getForDataTable();
        $data['statusOptions'] = $this->enquiryService->getStatusOptions();
        $data['packageOptions'] = $this->packageService->getForFilter();

        return Inertia::render('enquiries/index', [
            'data' => $data,
        ]);
    }

    /**
     * Get enquiry data for the show modal (JSON).
     */
    public function getForShow(string $id)
    {
        $enquiry = $this->enquiryService->getById((int) $id);

        $child = $enquiry->type === 'general'
            ? $this->enquiryService->generalEnquiryService->getForEditShow($enquiry->generalEnquiry->id)
            : $this->enquiryService->privateEnquiryService->getForEditShow($enquiry->privateEnquiry->id);

        return response()->json([
            'enquiry' => [
                'id' => $enquiry->id,
                'type' => $enquiry->type,
                'status' => $enquiry->status->value,
                'status_label' => $enquiry->status->label(),
            ],
            'child' => $child,
            'customerGroup' => $enquiry->customerGroup
                ? $this->customerGroupService->getByEnquiryId($enquiry->id)
                : null,
        ]);
    }

    /**
     * Transition an enquiry's status.
     */
    public function transitionStatus(Request $request, string $id)
    {
        $validated = $request->validate([
            'status' => ['required', 'string'],
        ]);

        $this->enquiryService->transitionStatus((int) $id, $validated['status']);

        return back()->with('success', 'Enquiry status updated successfully.');
    }

    /**
     * Create a customer group to confirm the enquiry.
     */
    public function confirm(Request $request, string $id)
    {
        $validated = $request->validate(array_merge(
            $this->customerGroupRule->rules(),
            ['terms_accepted' => ['sometimes', 'accepted']],
        ));

        $validated['enquiry_id'] = (int) $id;

        $this->customerGroupService->createGroup($validated);

        // Only transition status if not already confirmed
        $enquiry = $this->enquiryService->getById((int) $id);
        if ($enquiry->status !== \App\Enums\EnquiryStatus::Confirmed) {
            $this->enquiryService->transitionStatus((int) $id, 'confirmed');
        }

        return back()->with('success', 'Customer group created and enquiry confirmed successfully.');
    }

    /**
     * Create a customer group (standalone or linked to an enquiry).
     */
    public function createCustomerGroup(Request $request)
    {
        $validated = $request->validate(array_merge(
            $this->customerGroupRule->rules(requireEnquiry: false),
            [
                'terms_accepted' => ['sometimes', 'accepted'],
                'enquiry_id' => ['nullable', 'integer', 'exists:enquiries,id'],
            ],
        ));

        $this->customerGroupService->createGroup($validated);

        return back()->with('success', 'Customer group created successfully.');
    }

    /**
     * Update the package assigned to an enquiry.
     */
    public function updatePackage(Request $request, string $id)
    {
        $validated = $request->validate([
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
        ]);

        $this->enquiryService->updatePackage((int) $id, $validated['package_id'] ?? null);

        return back()->with('success', 'Enquiry package updated successfully.');
    }

    /**
     * Search for existing customers (for autocomplete).
     */
    public function searchCustomers(Request $request)
    {
        $query = $request->input('q', '');

        return response()->json(
            $this->customerGroupService->searchCustomers($query)
        );
    }

    /**
     * Get confirmed enquiries that don't have a customer group yet.
     */
    public function availableEnquiries()
    {
        return response()->json(
            $this->enquiryService->getConfirmedWithoutGroup()
        );
    }

    /**
     * List active customers for the select dropdown.
     */
    public function listCustomers()
    {
        return response()->json(
            $this->customerGroupService->listActiveCustomers()
        );
    }
}
