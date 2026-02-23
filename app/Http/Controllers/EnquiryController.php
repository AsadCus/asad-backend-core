<?php

namespace App\Http\Controllers;

use App\Rules\CustomerGroupRule;
use App\Rules\PackageRule;
use App\Services\CustomerGroupService;
use App\Services\EnquiryService;
use App\Services\PackageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                'name' => $enquiry->name,
                'email' => $enquiry->email,
                'contact_number' => $enquiry->contact_number,
                'package_name' => $enquiry->package?->name,
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
     * Create a customer group to confirm the enquiry (atomic: status + group creation).
     * For private enquiries the frontend also sends package_data which is used
     * to create a brand-new package inside the same transaction.
     */
    public function confirm(Request $request, string $id)
    {
        $packageRule = app(PackageRule::class);

        // Build validation rules — include package_data.* when present
        $rules = array_merge(
            $this->customerGroupRule->rules(),
            ['terms_accepted' => ['sometimes', 'accepted']],
        );

        if ($request->has('package_data')) {
            foreach ($packageRule->rules() as $key => $value) {
                $rules["package_data.{$key}"] = $value;
            }
            $rules['package_data'] = ['required', 'array'];
            $rules['package_data.accommodations'] = ['nullable', 'array'];
            $rules['package_data.accommodations.*.location'] = ['required', 'string', 'max:255'];
            $rules['package_data.accommodations.*.hotel_name'] = ['required', 'string', 'max:255'];
            $rules['package_data.accommodations.*.type_of_meal'] = ['nullable', 'string', 'max:255'];
            $rules['package_data.accommodations.*.check_in'] = ['nullable', 'date'];
            $rules['package_data.accommodations.*.check_out'] = ['nullable', 'date'];
        }

        $validated = $request->validate($rules);
        $validated['enquiry_id'] = (int) $id;

        return DB::transaction(function () use ($validated, $id) {
            // Atomically transition status to confirmed
            $this->enquiryService->confirmEnquiry((int) $id);

            // If package_data was supplied (private enquiry flow), create the package first
            if (! empty($validated['package_data'])) {
                $package = $this->packageService->store($validated['package_data']);
                $validated['package_id'] = $package->id;

                // Link the package to the enquiry
                $this->enquiryService->updatePackage((int) $id, $package->id);
            }

            // Create the customer group
            $this->customerGroupService->createGroup($validated);

            return back()->with('success', 'Customer group created and enquiry confirmed successfully.');
        });
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
     * Return package prefill data derived from a private enquiry.
     */
    public function packagePrefill(string $id)
    {
        $enquiry = $this->enquiryService->getById((int) $id);

        if ($enquiry->type !== 'private' || ! $enquiry->privateEnquiry) {
            return response()->json([], 200);
        }

        $pe = $enquiry->privateEnquiry;
        $totalSeats = ($pe->no_of_pax ?? 0) + ($pe->no_of_children ?? 0);

        return response()->json([
            'name' => 'Private - '.($enquiry->name ?? 'Unnamed'),
            'status' => 'open',
            'airline' => $pe->airline,
            'departure_date' => $pe->departure_date?->format('d/m/Y'),
            'arrival_date' => $pe->return_date?->format('d/m/Y'),
            'total_seats' => $totalSeats,
            'seats_left' => $totalSeats,
            'vehicle_type' => $pe->land_transfer,
            'ticket_type' => $pe->add_on_speed_train ? 'speed_train' : null,
            'remarks' => $pe->other_remarks,
            'accommodations' => array_values(array_filter([
                $pe->hotel_makkah ? [
                    'location' => 'Makkah',
                    'hotel_name' => $pe->hotel_makkah,
                    'type_of_meal' => $pe->meals_makkah,
                    'check_in' => null,
                    'check_out' => null,
                ] : null,
                $pe->hotel_madinah ? [
                    'location' => 'Madinah',
                    'hotel_name' => $pe->hotel_madinah,
                    'type_of_meal' => $pe->meals_madinah,
                    'check_in' => null,
                    'check_out' => null,
                ] : null,
            ])),
        ]);
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
