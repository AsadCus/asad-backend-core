<?php

namespace App\Http\Controllers;

use App\Rules\CustomerGroupRule;
use App\Services\CustomerGroupService;
use App\Services\PackageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;

class CustomerGroupController extends Controller
{
    public function __construct(
        protected CustomerGroupService $customerGroupService,
        protected CustomerGroupRule $customerGroupRule,
        protected PackageService $packageService,
    ) {}

    /**
     * Get customer group data for view/edit (JSON).
     */
    public function show(string $id)
    {
        return response()->json(
            $this->customerGroupService->getForEditShow((int) $id)
        );
    }

    /**
     * Update a customer group and its members.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate(array_merge(
            $this->customerGroupRule->rules(requireEnquiry: false),
            [
                'enquiry_id' => ['nullable', 'integer', 'exists:enquiries,id'],
            ],
        ));

        $this->customerGroupService->updateGroup((int) $id, $validated);

        return back()->with('success', 'Customer group updated successfully.');
    }

    /**
     * Generate a signed URL for the public customer form.
     */
    public function generatePublicLink(string $enquiryId)
    {
        $url = URL::signedRoute('customer-confirmation.public.create', ['enquiryId' => $enquiryId]);

        return response()->json(['url' => $url]);
    }

    /**
     * Show the public create form (standalone, no enquiry).
     */
    public function publicCreateForm(Request $request)
    {
        $packageOptions = $this->packageService->getForFilter();

        return inertia('customer/public/index', [
            'mode' => 'create',
            'packageOptions' => $packageOptions,
            'publicSubmitUrl' => route('customer-confirmation.public.store'),
        ]);
    }

    /**
     * Store a customer group from the public create form.
     */
    public function publicCreateStore(Request $request)
    {
        $validated = $request->validate(array_merge(
            $this->customerGroupRule->rules(requireEnquiry: false),
            ['terms_accepted' => ['required', 'accepted']],
        ));

        $this->customerGroupService->createGroup($validated);

        return back()->with('success', 'Your application has been submitted successfully.');
    }

    /**
     * Show the public edit form for an existing customer group.
     */
    public function publicEditForm(Request $request, string $encryptedId)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired link.');
        }

        try {
            $groupId = Crypt::decrypt($encryptedId);
        } catch (\Exception $e) {
            abort(404, 'Invalid group identifier.');
        }

        $groupData = $this->customerGroupService->getForEditShow((int) $groupId);
        $packageOptions = $this->packageService->getForFilter();

        return inertia('customer/public/index', [
            'mode' => 'edit',
            'groupId' => $groupId,
            'initialData' => $groupData,
            'packageOptions' => $packageOptions,
            'publicSubmitUrl' => URL::signedRoute('customer-confirmation.public.update', ['encryptedId' => $encryptedId]),
        ]);
    }

    /**
     * Update a customer group from the public edit form.
     */
    public function publicEditStore(Request $request, string $encryptedId)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired link.');
        }

        try {
            $groupId = Crypt::decrypt($encryptedId);
        } catch (\Exception $e) {
            abort(404, 'Invalid group identifier.');
        }

        $validated = $request->validate(array_merge(
            $this->customerGroupRule->rules(requireEnquiry: false),
            ['terms_accepted' => ['required', 'accepted']],
        ));

        $this->customerGroupService->updateGroup((int) $groupId, $validated);

        return back()->with('success', 'Your application has been updated successfully.');
    }

    /**
     * Generate a signed URL for public edit link (copy from customer group index).
     */
    public function generatePublicEditLink(string $groupId)
    {
        $encryptedId = Crypt::encrypt($groupId);
        $url = URL::signedRoute('customer-confirmation.public.edit', ['encryptedId' => $encryptedId]);

        return response()->json(['url' => $url]);
    }
}
