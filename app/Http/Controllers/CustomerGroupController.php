<?php

namespace App\Http\Controllers;

use App\Rules\CustomerGroupRule;
use App\Services\CustomerGroupService;
use App\Services\PackageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CustomerGroupController extends Controller
{
    public function __construct(
        protected CustomerGroupService $customerGroupService,
        protected CustomerGroupRule $customerGroupRule,
        protected PackageService $packageService,
    ) {}

    /**
     * Display a listing of confirmed customer groups.
     */
    public function index()
    {
        $dataGroups = $this->customerGroupService->getForGroupedIndex();
        $packageOptions = $this->packageService->getForFilter();

        return Inertia::render('confirmed-customer/index', [
            'dataGroups' => $dataGroups,
            'packageOptions' => $packageOptions,
        ]);
    }

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
     * Delete a customer group and its members.
     */
    public function destroy(Request $request, string $id)
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $groupId) {
                $this->customerGroupService->deleteGroup((int) $groupId);
            }

            return redirect()->intended(route('confirmed-customer.index'))->with('success', 'Selected customer groups deleted successfully.');
        }

        $this->customerGroupService->deleteGroup((int) $id);

        return redirect()->intended(route('confirmed-customer.index'))->with('success', 'Customer group deleted successfully.');
    }

    /**
     * Generate a signed URL for the public customer form.
     */
    public function generatePublicLink(Request $request, string $enquiryId)
    {
        $linkType = $this->normalizeLinkType($request->query('link_type'));
        $url = URL::signedRoute('customer-confirmation.public.create', [
            'enquiryId' => $enquiryId,
            'link_type' => $linkType,
        ]);

        activity()
            ->causedBy($request->user())
            ->withProperties([
                'subject_type' => 'Enquiry',
                'subject_id' => (int) $enquiryId,
                'link_type' => $linkType,
            ])
            ->log('Public customer confirmation create link generated');

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

        $linkType = $this->normalizeLinkType($request->query('link_type'));
        $oneTimeToken = null;

        if ($linkType === 'one_time') {
            $oneTimeToken = $this->ensureValidOneTimeEditToken($request, (int) $groupId);
        }

        $groupData = $this->customerGroupService->getForEditShow((int) $groupId);
        $packageOptions = $this->packageService->getForFilter();

        $linkExpiresAt = is_numeric($request->query('expires'))
            ? now()->setTimestamp((int) $request->query('expires'))
            : now()->addHours(24);

        $publicSubmitUrl = $linkType === 'one_time'
            ? URL::temporarySignedRoute(
                'customer-confirmation.public.update',
                $linkExpiresAt,
                [
                    'encryptedId' => $encryptedId,
                    'link_type' => 'one_time',
                    'link_token' => $oneTimeToken,
                ],
            )
            : URL::signedRoute('customer-confirmation.public.update', [
                'encryptedId' => $encryptedId,
                'link_type' => 'continuous',
            ]);

        return inertia('customer/public/index', [
            'mode' => 'edit',
            'groupId' => $groupId,
            'initialData' => $groupData,
            'packageOptions' => $packageOptions,
            'linkType' => $linkType,
            'publicSubmitUrl' => $publicSubmitUrl,
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

        $linkType = $this->normalizeLinkType($request->query('link_type'));
        $oneTimeToken = null;

        if ($linkType === 'one_time') {
            $oneTimeToken = $this->ensureValidOneTimeEditToken($request, (int) $groupId);
        }

        $validated = $request->validate(array_merge(
            $this->customerGroupRule->rules(requireEnquiry: false),
            ['terms_accepted' => ['required', 'accepted']],
        ));

        $this->customerGroupService->updateGroup((int) $groupId, $validated);

        if ($linkType === 'one_time' && $oneTimeToken) {
            Cache::forget($this->oneTimeEditLinkCacheKey($oneTimeToken));

            return inertia('customer/public/index', [
                'mode' => 'edit',
                'linkType' => 'one_time',
                'oneTimeCompleted' => true,
                'successTitle' => 'Update complete',
                'successDescription' => 'This one-time link has already been used and can no longer be accessed.',
            ]);
        }

        return back()->with('success', 'Your application has been updated successfully.');
    }

    /**
     * Generate a signed URL for public edit link (copy from customer group index).
     */
    public function generatePublicEditLink(Request $request, string $groupId)
    {
        $linkType = $this->normalizeLinkType($request->query('link_type'));
        $encryptedId = Crypt::encrypt($groupId);

        if ($linkType === 'one_time') {
            $expiresAt = now()->addHours(24);
            $token = (string) Str::uuid();

            Cache::put($this->oneTimeEditLinkCacheKey($token), [
                'group_id' => (int) $groupId,
            ], $expiresAt);

            $url = URL::temporarySignedRoute('customer-confirmation.public.edit', $expiresAt, [
                'encryptedId' => $encryptedId,
                'link_type' => 'one_time',
                'link_token' => $token,
            ]);
        } else {
            $url = URL::signedRoute('customer-confirmation.public.edit', [
                'encryptedId' => $encryptedId,
                'link_type' => 'continuous',
            ]);
        }

        activity()
            ->causedBy($request->user())
            ->withProperties([
                'subject_type' => 'CustomerGroup',
                'subject_id' => (int) $groupId,
                'link_type' => $linkType,
            ])
            ->log('Public customer confirmation edit link generated');

        return response()->json(['url' => $url]);
    }

    private function normalizeLinkType(?string $linkType): string
    {
        return $linkType === 'one_time' ? 'one_time' : 'continuous';
    }

    private function oneTimeEditLinkCacheKey(string $token): string
    {
        return "customer-group-public-edit-link:{$token}";
    }

    private function ensureValidOneTimeEditToken(Request $request, int $groupId): string
    {
        $token = (string) $request->query('link_token', '');

        if ($token === '') {
            abort(403, 'Invalid or expired link.');
        }

        $payload = Cache::get($this->oneTimeEditLinkCacheKey($token));

        if (! is_array($payload) || (int) ($payload['group_id'] ?? 0) !== $groupId) {
            abort(403, 'Invalid or expired link.');
        }

        return $token;
    }
}
