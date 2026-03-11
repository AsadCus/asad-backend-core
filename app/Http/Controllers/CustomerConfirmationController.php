<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerConfirmation\GenerateQuotationsRequest;
use App\Models\CustomerConfirmation;
use App\Rules\CustomerConfirmationRule;
use App\Services\CustomerConfirmationService;
use App\Services\PackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CustomerConfirmationController extends Controller
{
    public function __construct(
        protected CustomerConfirmationService $customerConfirmationService,
        protected CustomerConfirmationRule $customerConfirmationRule,
        protected PackageService $packageService,
    ) {}

    /**
     * Display a listing of confirmed customer confirmations.
     */
    public function index()
    {
        $dataGroups = $this->customerConfirmationService->getForGroupedIndex(true);
        $packageOptions = $this->packageService->getForFilter();

        return Inertia::render('confirmed-customer/index', [
            'dataGroups' => $dataGroups,
            'packageOptions' => $packageOptions,
            'pageTitle' => 'Confirmed Customers',
            'indexUrl' => route('confirmed-customer.index'),
        ]);
    }

    /**
     * Display a listing of holding customer confirmations.
     */
    public function holdingIndex()
    {
        $dataGroups = $this->customerConfirmationService->getForGroupedIndex(false);
        $packageOptions = $this->packageService->getForFilter();

        return Inertia::render('confirmed-customer/index', [
            'dataGroups' => $dataGroups,
            'packageOptions' => $packageOptions,
            'pageTitle' => 'Customer Holding',
            'indexUrl' => route('customer-holding.index'),
        ]);
    }

    /**
     * Get customer confirmation data for view/edit (JSON).
     */
    public function show(string $id)
    {
        return response()->json(
            $this->customerConfirmationService->getForEditShow((int) $id)
        );
    }

    /**
     * Update a customer confirmation and its members.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate(array_merge(
            $this->customerConfirmationRule->rules(requireEnquiry: false),
            [
                'enquiry_id' => ['nullable', 'integer', 'exists:enquiries,id'],
            ],
        ));

        $this->customerConfirmationService->updateGroup((int) $id, $validated);

        return back()->with('success', 'Customer confirmation updated successfully.');
    }

    /**
     * Delete a customer confirmation and its members.
     */
    public function destroy(Request $request, string $id)
    {
        $ids = $request->input('ids');
        $redirectTarget = $this->resolveIndexRedirectTarget();

        if ($ids && is_array($ids)) {
            foreach ($ids as $groupId) {
                $this->customerConfirmationService->deleteGroup((int) $groupId);
            }

            return back(fallback: $redirectTarget)->with('success', 'Selected customer confirmations deleted successfully.');
        }

        $this->customerConfirmationService->deleteGroup((int) $id);

        return back(fallback: $redirectTarget)->with('success', 'Customer confirmation deleted successfully.');
    }

    /**
     * Move selected members from an existing confirmation into a new holding confirmation.
     */
    public function moveMembers(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:customer_confirmation_members,id'],
            'target_package_id' => ['nullable', 'integer', 'exists:packages,id'],
            'source_manifest_id' => ['nullable', 'integer', 'exists:manifests,id'],
        ]);

        $newConfirmation = $this->customerConfirmationService->moveMembersToHolding(
            (int) $id,
            $validated['member_ids'],
            $validated['target_package_id'] ?? null,
            $validated['source_manifest_id'] ?? null,
        );

        return response()->json([
            'message' => 'Customer members moved to holding confirmation successfully.',
            'new_confirmation_id' => $newConfirmation->id,
        ]);
    }

    /**
     * Update one customer confirmation member profile/status/sharing plan.
     */
    public function updateMember(Request $request, string $memberId): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'contact_number' => ['required', 'string', 'max:30'],
            'nric_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'passport_number' => ['nullable', 'string', 'max:50'],
            'passport_issue_date' => ['nullable', 'date'],
            'passport_expiry_date' => ['nullable', 'date'],
            'passport_place_of_issue' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'in:male,female'],
            'marital_status' => ['nullable', 'string', 'in:single,married,divorced,widowed'],
            'date_of_birth' => ['nullable', 'date'],
            'place_of_birth' => ['nullable', 'string', 'max:255'],
            'first_time_umrah' => ['nullable', 'boolean'],
            'has_chronic_disease' => ['nullable', 'boolean'],
            'chronic_disease_details' => ['nullable', 'string', 'max:1000'],
            'passport_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'photo_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'passport_path' => ['nullable', 'string'],
            'photo_path' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:draft,pending_payment,partially_paid,confirmed,cancelled,unavailable'],
            'sharing_plan' => ['nullable', 'string', 'in:single,double,triple,quad'],
            'role' => ['nullable', 'string', 'max:255'],
        ]);

        $member = $this->customerConfirmationService->updateMemberDetails((int) $memberId, $validated);

        return response()->json([
            'message' => 'Member updated successfully.',
            'member' => $member,
        ]);
    }

    /**
     * Mark one confirmation member as cancelled.
     */
    public function cancelMember(string $memberId): JsonResponse
    {
        $this->customerConfirmationService->cancelMember((int) $memberId);

        return response()->json([
            'message' => 'Member cancelled successfully.',
        ]);
    }

    /**
     * Generate quotation(s) from a customer confirmation.
     *
     * Accepts a payer-to-members mapping and creates one quotation per payer.
     */
    public function generateQuotations(GenerateQuotationsRequest $request, string $id): JsonResponse
    {
        CustomerConfirmation::query()->findOrFail((int) $id);

        $validated = $request->validated();

        $quotations = $this->customerConfirmationService->generateQuotationsFromConfirmation(
            (int) $id,
            $validated['payer_to_members'],
        );

        return response()->json([
            'message' => count($quotations).' quotation(s) created successfully.',
            'quotation_ids' => array_map(fn ($q) => $q->id, $quotations),
        ]);
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
     * Store a customer confirmation from the public create form.
     */
    public function publicCreateStore(Request $request)
    {
        $validated = $request->validate(array_merge(
            $this->customerConfirmationRule->rules(requireEnquiry: false),
            ['terms_accepted' => ['required', 'accepted']],
        ));

        $this->customerConfirmationService->createGroup($validated);

        return back()->with('success', 'Your application has been submitted successfully.');
    }

    /**
     * Show the public edit form for an existing customer confirmation.
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

        $groupData = $this->customerConfirmationService->getForEditShow((int) $groupId);
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
     * Update a customer confirmation from the public edit form.
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
            $this->customerConfirmationRule->rules(requireEnquiry: false),
            ['terms_accepted' => ['required', 'accepted']],
        ));

        $this->customerConfirmationService->updateGroup((int) $groupId, $validated);

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
     * Generate a signed URL for public edit link (copy from customer confirmation index).
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
                'subject_type' => 'CustomerConfirmation',
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
        return "customer-confirmation-public-edit-link:{$token}";
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

    private function resolveIndexRedirectTarget(): string
    {
        $previousPath = parse_url(url()->previous(), PHP_URL_PATH);

        if (is_string($previousPath) && Str::startsWith($previousPath, '/customer-holding')) {
            return route('customer-holding.index');
        }

        return route('confirmed-customer.index');
    }
}
