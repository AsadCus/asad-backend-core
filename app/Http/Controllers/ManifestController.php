<?php

namespace App\Http\Controllers;

use App\Helpers\ArabicTextHelper;
use App\Http\Requests\ManifestImportRequest;
use App\Models\CustomerConfirmationMember;
use App\Models\Manifest;
use App\Models\ManifestMember;
use App\Models\ModelFile;
use App\Models\Package;
use App\Rules\ManifestRule;
use App\Services\CustomerConfirmationService;
use App\Services\ManifestImportService;
use App\Services\ManifestService;
use App\Services\PackageSeatService;
use App\Services\PackageService;
use App\Services\Report\ReportTemplateService;
use App\Support\DataScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ManifestController extends Controller
{
    public function __construct(
        protected ManifestService $manifestService,
        protected ManifestRule $manifestRule,
        protected PackageService $packageService,
        protected CustomerConfirmationService $customerConfirmationService,
        protected ReportTemplateService $reportTemplateService,
    ) {
        $this->middleware('permission:manifest view')->only([
            'index', 'show', 'edit', 'getForShow',
            'exportCollectionItemsPdf', 'exportArabicNamesPdf',
            'exportAirlineNamesPdf', 'exportRoomCheckPdf',
        ]);
        $this->middleware('permission:manifest edit')->only([
            'store', 'import', 'destroy',
            'addRoom', 'updateRoom', 'deleteRoom',
            'updateCoreSection', 'updateSharingGroupsSection', 'updateRoomsSection',
            'updateDocumentsSection', 'updateReceiptDocumentsSection',
            'attachSharingGroup', 'detachSharingGroup',
            'moveMemberToHolding',
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $data['manifestsForDatatable'] = $this->manifestService->getForDataTable();

        return Inertia::render('manifests/index', [
            'data' => $data,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $requestPayload = array_replace_recursive($request->all(), $request->allFiles());
        $manifestId = isset($requestPayload['id']) ? (int) $requestPayload['id'] : 0;
        $requestedTab = trim((string) $request->input('tab', ''));
        $shouldStayOnForm = $request->boolean('stay_on_form');
        $normalizedPayload = $this->normalizeManifestPayload($requestPayload);
        $validated = validator(
            $normalizedPayload,
            $this->manifestRule->rules($manifestId > 0 ? $manifestId : null),
        )->validate();

        $validated['roomLists'] = $normalizedPayload['roomLists'] ?? [];
        $validated['documents'] = $normalizedPayload['documents'] ?? [];
        $this->ensureMemberPackageMatchesManifestPackage($validated);

        if ($manifestId > 0) {
            $updatedManifest = $this->manifestService->update($validated, $manifestId);

            if ($shouldStayOnForm) {
                return redirect()->route('manifests.edit', [
                    'manifest' => $updatedManifest->id,
                    'tab' => $requestedTab !== '' ? $requestedTab : null,
                ])->with('success', 'Manifest updated successfully.');
            }

            return redirect()->route('manifests.index')
                ->with('success', 'Manifest updated successfully.');
        }

        $createdManifest = $this->manifestService->store($validated);

        if ($shouldStayOnForm) {
            return redirect()->route('manifests.edit', [
                'manifest' => $createdManifest->id,
                'tab' => $requestedTab !== '' ? $requestedTab : null,
            ])->with('success', 'Manifest created successfully.');
        }

        return redirect()->route('manifests.index')
            ->with('success', 'Manifest created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): Response
    {
        $manifest = $this->manifestService->getForEditShow($id);
        $dataPackage = $this->packageService->getForFilter();

        return Inertia::render('manifests/show', [
            'data' => $manifest,
            'dataPackage' => $dataPackage,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id): Response
    {
        $manifest = $this->manifestService->getForEditShow($id);
        $dataPackage = $this->packageService->getForFilter();

        return Inertia::render('manifests/edit', [
            'data' => $manifest,
            'dataPackage' => $dataPackage,
        ]);
    }

    /**
     * Import a batch of manifest members (with full chain: customer, confirmation,
     * quotation, order, invoice, receipt) from a parsed Excel payload.
     */
    public function import(
        ManifestImportRequest $request,
        ManifestImportService $importService,
        string $id,
    ): RedirectResponse {
        $manifest = Manifest::findOrFail($id);

        $result = $importService->importFromPayload(
            $manifest,
            (array) $request->input('context', []),
            (array) $request->input('members', $request->input('data', [])),
            (array) $request->input('payments', []),
        );

        $summary = "Imported {$result['imported_members']} member(s) across {$result['bookings']} booking(s): "
            ."{$result['quotations']} quotation(s), {$result['invoices']} invoice(s), {$result['receipts']} receipt(s).";

        if (! empty($result['errors'])) {
            // Successful bookings are already committed; report the failures while
            // still telling the user what got through (partial-success semantics).
            $errorLines = collect($result['errors'])
                ->map(function ($e) {
                    $location = $e['booking_ref'] ? "Booking {$e['booking_ref']}" : 'Import';
                    if (! empty($e['row'])) {
                        $location .= " (row {$e['row']})";
                    }

                    return "{$location}: {$e['message']}";
                })
                ->join(' | ');

            throw ValidationException::withMessages([
                'import' => "{$summary} Some bookings failed — {$errorLines}",
            ]);
        }

        return redirect()->route('manifests.edit', ['manifest' => $manifest->id])
            ->with('success', $summary);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $ids = $request->input('ids');
        if ($ids && is_array($ids)) {
            foreach ($ids as $manifestId) {
                $this->manifestService->delete($manifestId);
            }

            return redirect()->route('manifests.index')
                ->with('success', 'Selected manifests deleted successfully.');
        }

        $this->manifestService->delete($id);

        return redirect()->route('manifests.index')
            ->with('success', 'Manifest deleted successfully.');
    }

    /**
     * Add a room to a manifest.
     */
    public function addRoom(Request $request, string $manifestId): JsonResponse
    {
        $validated = $request->validate($this->manifestRule->roomRules());
        $room = $this->manifestService->addRoom((int) $manifestId, $validated);

        return response()->json(['room' => $room], 201);
    }

    /**
     * Update a room.
     */
    public function updateRoom(Request $request, string $roomId): JsonResponse
    {
        $validated = $request->validate($this->manifestRule->roomRules());
        $room = $this->manifestService->updateRoom((int) $roomId, $validated);

        return response()->json(['room' => $room]);
    }

    /**
     * Delete a room.
     */
    public function deleteRoom(string $roomId): JsonResponse
    {
        $this->manifestService->deleteRoom((int) $roomId);

        return response()->json(['message' => 'Room deleted successfully.']);
    }

    public function updateCoreSection(Request $request, string $manifestId): JsonResponse
    {
        $manifest = $this->resolveScopedManifest((int) $manifestId);
        $payload = [
            'id' => $manifest->id,
            'package_id' => $manifest->package_id,
        ];
        $input = $request->all();
        $allowedFields = ['package_id', 'in_charge_official_id', 'notes', 'status'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $payload[$field] = $input[$field];
            }
        }

        if (count($payload) === 1) {
            throw ValidationException::withMessages([
                'section' => 'At least one manifest core field must be provided.',
            ]);
        }

        $rules = $this->extractRulesByPrefix(
            $this->manifestRule->rules($manifest->id),
            $allowedFields,
        );

        $validated = validator($payload, $rules)->validate();

        if ($request->boolean('validate_only')) {
            return response()->json([
                'message' => 'Manifest core section validated successfully.',
                'manifest_id' => $manifest->id,
                'validated' => true,
            ]);
        }

        $updatedManifest = $this->manifestService->update($validated, $manifest->id);
        // dd($updatedManifest);

        return response()->json([
            'message' => 'Manifest core section updated successfully.',
            'manifest_id' => $updatedManifest->id,
        ]);
    }

    public function updateSharingGroupsSection(Request $request, string $manifestId): JsonResponse
    {
        $manifest = $this->resolveScopedManifest((int) $manifestId);
        $requestPayload = array_replace_recursive($request->all(), $request->allFiles());
        $normalized = $this->normalizeSharingGroupsSectionPayload($requestPayload, $manifest->id);
        $rules = $this->extractRulesByPrefix(
            $this->manifestRule->rules($manifest->id),
            ['members'],
        );
        $validated = validator($normalized, $rules)->validate();
        $validated['package_id'] = $manifest->package_id;
        $this->ensureMemberPackageMatchesManifestPackage($validated);

        if ($request->boolean('validate_only')) {
            return response()->json([
                'message' => 'Manifest sharing-groups section validated successfully.',
                'manifest_id' => $manifest->id,
                'validated' => true,
            ]);
        }

        $this->manifestService->update($validated, $manifest->id);

        return response()->json([
            'message' => 'Manifest sharing-groups section updated successfully.',
            'manifest_id' => $manifest->id,
        ]);
    }

    public function updateRoomsSection(Request $request, string $manifestId): JsonResponse
    {
        $manifest = $this->resolveScopedManifest((int) $manifestId);
        $requestPayload = array_replace_recursive($request->all(), $request->allFiles());
        $normalized = $this->normalizeRoomsSectionPayload($requestPayload, $manifest->id);
        $rules = $this->extractRulesByPrefix(
            $this->manifestRule->rules($manifest->id),
            ['rooms'],
        );
        $validated = validator($normalized, $rules)->validate();

        if ($request->boolean('validate_only')) {
            return response()->json([
                'message' => 'Manifest rooms section validated successfully.',
                'manifest_id' => $manifest->id,
                'validated' => true,
            ]);
        }

        $this->manifestService->update($validated, $manifest->id);

        return response()->json([
            'message' => 'Manifest rooms section updated successfully.',
            'manifest_id' => $manifest->id,
        ]);
    }

    public function updateDocumentsSection(Request $request, string $manifestId): JsonResponse
    {
        $manifest = $this->resolveScopedManifest((int) $manifestId);
        $requestPayload = array_replace_recursive($request->all(), $request->allFiles());
        $normalized = $this->normalizeDocumentsSectionPayload($requestPayload, $manifest->id);
        $rules = $this->extractRulesByPrefix(
            $this->manifestRule->rules($manifest->id),
            ['documents'],
        );
        $validated = validator($normalized, $rules)->validate();

        if ($request->boolean('validate_only')) {
            return response()->json([
                'message' => 'Manifest documents section validated successfully.',
                'manifest_id' => $manifest->id,
                'validated' => true,
            ]);
        }

        $this->manifestService->update($validated, $manifest->id);

        return response()->json([
            'message' => 'Manifest documents section updated successfully.',
            'manifest_id' => $manifest->id,
        ]);
    }

    public function updateReceiptDocumentsSection(Request $request, string $manifestId): JsonResponse
    {
        $manifest = $this->resolveScopedManifest((int) $manifestId);
        $requestPayload = array_replace_recursive($request->all(), $request->allFiles());
        $normalized = $this->normalizeReceiptDocumentsSectionPayload($requestPayload, $manifest->id);
        $rules = $this->extractRulesByPrefix(
            $this->manifestRule->rules($manifest->id),
            ['manifest_member_receipts'],
        );
        $validated = validator($normalized, $rules)->validate();

        if ($request->boolean('validate_only')) {
            return response()->json([
                'message' => 'Manifest receipt-documents section validated successfully.',
                'manifest_id' => $manifest->id,
                'validated' => true,
            ]);
        }

        $this->manifestService->syncMemberReceiptDocumentsSection(
            $manifest,
            $validated['manifest_member_receipts'] ?? [],
        );

        return response()->json([
            'message' => 'Manifest receipt-documents section updated successfully.',
            'manifest_id' => $manifest->id,
        ]);
    }

    /**
     * Attach a sharing group to a manifest.
     */
    public function attachSharingGroup(Request $request, string $manifestId): JsonResponse
    {
        $validated = $request->validate([
            'customer_confirmation_id' => ['required', 'integer', 'exists:customer_confirmations,id'],
        ]);

        $msg = $this->manifestService->attachSharingGroup(
            (int) $manifestId,
            $validated['customer_confirmation_id'],
        );

        return response()->json(['manifest_sharing_group' => $msg], 201);
    }

    /**
     * Detach a sharing group from a manifest.
     */
    public function detachSharingGroup(string $manifestId, string $sharingGroupId): JsonResponse
    {
        $this->manifestService->detachSharingGroup((int) $manifestId, (int) $sharingGroupId);

        return response()->json(['message' => 'Sharing group detached successfully.']);
    }

    /**
     * Get manifest data as JSON (for dialog previews).
     */
    public function getForShow(string $id): JsonResponse
    {
        $manifest = $this->manifestService->getForEditShow($id);

        return response()->json($manifest);
    }

    public function exportCollectionItemsPdf(Request $request, string $id)
    {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(60);

            $manifest = $this->manifestService->getForEditShow((int) $id);

            $reportData = $this->reportTemplateService->build('manifest_namelist_course_items', [
                'manifest' => $manifest,
            ]);

            $html = view('manifests.namelist-course-items-report-content', [
                'manifest' => $manifest,
                'branding' => $reportData['branding'],
                'is_pdf' => true,
            ])->render();

            $manifestNumber = trim((string) ($manifest['manifest_number'] ?? 'Manifest'));
            $fileName = 'Course Attendance and Items Collection - '.$manifestNumber.'.pdf';

            return Pdf::loadHTML($html)
                ->setPaper('a4', 'landscape')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('dpi', 96)
                ->stream($fileName);
        } catch (\Throwable $e) {
            Log::error('Manifest collection-items PDF generation error', [
                'manifest_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to generate PDF: '.$e->getMessage(),
            ], 500);
        }
    }

    public function exportArabicNamesPdf(Request $request, string $id)
    {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(60);

            $manifest = $this->manifestService->getForEditShow((int) $id);

            $manifest['members'] = collect($manifest['members'] ?? [])
                ->map(function ($member) {
                    if (! is_array($member)) {
                        return $member;
                    }

                    $member['arabic_name'] = ArabicTextHelper::shapeForPdf($member['arabic_name'] ?? null);

                    return $member;
                })
                ->all();

            $reportData = $this->reportTemplateService->build('manifest_arabic_names', [
                'manifest' => $manifest,
            ]);

            $html = view('manifests.arabic-names-report-content', [
                'manifest' => $manifest,
                'branding' => $reportData['branding'],
                'is_pdf' => true,
            ])->render();

            $manifestNumber = trim((string) ($manifest['manifest_number'] ?? 'Manifest'));
            $fileName = 'Manifest Arabic Names - '.$manifestNumber.'.pdf';

            return Pdf::loadHTML($html)
                ->setPaper('a4', 'landscape')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('dpi', 96)
                ->stream($fileName);
        } catch (\Throwable $e) {
            Log::error('Manifest arabic names PDF generation error', [
                'manifest_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to generate PDF: '.$e->getMessage(),
            ], 500);
        }
    }

    public function exportAirlineNamesPdf(Request $request, string $id)
    {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(60);

            $manifest = $this->manifestService->getForEditShow((int) $id);

            $reportData = $this->reportTemplateService->build('manifest_airline_names', [
                'manifest' => $manifest,
            ]);

            $html = view('manifests.airline-names-report-content', [
                'manifest' => $manifest,
                'branding' => $reportData['branding'],
                'is_pdf' => true,
            ])->render();

            $manifestNumber = trim((string) ($manifest['manifest_number'] ?? 'Manifest'));
            $fileName = 'Manifest Airline Names - '.$manifestNumber.'.pdf';

            return Pdf::loadHTML($html)
                ->setPaper('a4', 'landscape')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('dpi', 96)
                ->stream($fileName);
        } catch (\Throwable $e) {
            Log::error('Manifest airline names PDF generation error', [
                'manifest_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to generate PDF: '.$e->getMessage(),
            ], 500);
        }
    }

    public function exportRoomCheckPdf(Request $request, string $id)
    {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(60);

            $location = Str::of((string) $request->query('location', ''))
                ->trim()
                ->lower()
                ->value();

            if ($location === '') {
                return response()->json([
                    'error' => 'Room check location is required.',
                ], 422);
            }

            $manifest = $this->manifestService->getForEditShow((int) $id);

            $roomRows = collect((array) Arr::get($manifest, 'roomLists.'.$location, []))
                ->values()
                ->all();

            $locationLabel = collect((array) ($manifest['package_accommodations'] ?? []))
                ->first(function (array $accommodation) use ($location): bool {
                    return Str::of((string) ($accommodation['location'] ?? ''))
                        ->trim()
                        ->lower()
                        ->value() === $location;
                })['location'] ?? Str::title(str_replace('-', ' ', $location));

            $manifest['room_check_location'] = $location;
            $manifest['room_check_location_label'] = $manifest['room_check_location_label'] ?? $locationLabel;

            $roomCheckRows = isset($manifest['room_check_rows']) && is_array($manifest['room_check_rows'])
                ? $manifest['room_check_rows']
                : $roomRows;

            $manifest['room_check_rows'] = collect($roomCheckRows)
                ->filter(fn ($row) => is_array($row))
                ->map(fn (array $row): array => $this->sanitizeRoomCheckExportRow($row))
                ->values()
                ->all();

            $reportData = $this->reportTemplateService->build('manifest_room_check', [
                'manifest' => $manifest,
            ]);

            $html = view('manifests.room-check-report-content', [
                'manifest' => $manifest,
                'branding' => $reportData['branding'],
                'is_pdf' => true,
            ])->render();

            $manifestNumber = trim((string) ($manifest['manifest_number'] ?? 'Manifest'));
            $roomCheckLocationLabel = trim((string) ($manifest['room_check_location_label'] ?? '-'));
            $fileName = 'Manifest Check-In Room List - '.$roomCheckLocationLabel.' - '.$manifestNumber.'.pdf';

            return Pdf::loadHTML($html)
                ->setPaper('a4', 'landscape')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('dpi', 96)
                ->stream($fileName);
        } catch (\Throwable $e) {
            Log::error('Manifest room-check PDF generation error', [
                'manifest_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to generate PDF: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function sanitizeRoomCheckExportRow(array $row): array
    {
        $row['room_type'] = $this->normalizeRoomType($row['room_type'] ?? null) ?? $row['room_type'] ?? null;
        $row['bed_type'] = $this->normalizeBedType($row['bed_type'] ?? null) ?? $row['bed_type'] ?? null;

        return $row;
    }

    /**
     * Move one manifest member to a new holding confirmation and cancel existing assignment.
     */
    public function moveMemberToHolding(Request $request, string $manifestId, string $memberId): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'target_package_id' => ['nullable', 'integer', 'exists:packages,id'],
        ]);

        $manifestMember = ManifestMember::query()
            ->where('manifest_id', (int) $manifestId)
            ->where('id', (int) $memberId)
            ->firstOrFail();

        if (! $manifestMember->customer_confirmation_member_id) {
            throw ValidationException::withMessages([
                'member' => 'Only confirmation-linked members can be moved to holding.',
            ]);
        }

        $member = CustomerConfirmationMember::query()
            ->findOrFail((int) $manifestMember->customer_confirmation_member_id);

        $newConfirmation = $this->customerConfirmationService->moveMembersToHolding(
            (int) $member->customer_confirmation_id,
            [(int) $member->id],
            $validated['target_package_id'] ?? null,
            (int) $manifestId,
        );

        if ($request->header('X-Inertia')) {
            return redirect()->back(303)
                ->with('success', 'Member moved to holding confirmation successfully.');
        }

        return response()->json([
            'message' => 'Member moved to holding confirmation successfully.',
            'new_confirmation_id' => $newConfirmation->id,
        ]);
    }

    /**
     * Normalize grouped frontend payload into backend validation shape.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeManifestPayload(array $payload): array
    {
        if (! array_key_exists('members', $payload)) {
            $canonicalMembers = Arr::get($payload, 'manifest_members');

            if (is_array($canonicalMembers)) {
                $payload['members'] = $canonicalMembers;
            }
        }

        $this->applyCanonicalManifestFields($payload);
        $this->applyCanonicalMembers($payload);
        $this->applyCanonicalRoomLists($payload);

        $members = array_map(function (array $member): array {
            $member['room_type'] = $this->normalizeRoomType($member['room_type'] ?? null);
            $member['bed_type'] = $this->normalizeBedType($member['bed_type'] ?? null);
            $member['status'] = $this->normalizeMemberStatus($member['status'] ?? null);

            return $member;
        }, $this->flattenGroupedRows(Arr::get($payload, 'members', [])));
        $payload['members'] = $members;
        $payload['documents'] = $this->normalizeManifestDocuments(
            Arr::get($payload, 'documents', []),
        );

        $roomLists = Arr::get($payload, 'roomLists', []);

        if (! is_array($roomLists)) {
            $roomLists = [];
        }

        $roomLists = $this->normalizeRoomListsForUi($roomLists);

        $roomRows = $this->normalizeRoomRowsFromLists($roomLists, $members);

        if (! empty($roomRows)) {
            $payload['rooms'] = $roomRows;
        }

        $flightDetails = Arr::get($payload, 'flight_details', []);
        if (! is_array($flightDetails)) {
            $flightDetails = [];
        }

        $airlineList = $this->flattenGroupedRows(Arr::get($payload, 'airlineList', []));

        $flightDetails['ui_room_lists'] = $roomLists;
        $flightDetails['ui_airline_list'] = $airlineList;

        $payload['flight_details'] = $flightDetails;
        $payload['airlineList'] = $airlineList;
        $payload['roomLists'] = $roomLists;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyCanonicalManifestFields(array &$payload): void
    {
        $manifest = Arr::get($payload, 'manifest');

        if ($manifest === null) {
            return;
        }

        if (! is_array($manifest)) {
            throw ValidationException::withMessages([
                'manifest' => 'The manifest field must be an object when provided.',
            ]);
        }

        $fieldMap = [
            'id' => 'id',
            'package_id' => 'package_id',
            'in_charge_official_id' => 'in_charge_official_id',
            'manifest_number' => 'manifest_number',
            'status' => 'status',
            'notes' => 'notes',
        ];

        foreach ($fieldMap as $manifestKey => $payloadKey) {
            if (array_key_exists($manifestKey, $manifest)) {
                $payload[$payloadKey] = $manifest[$manifestKey];
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyCanonicalMembers(array &$payload): void
    {
        $canonicalGroups = Arr::get($payload, 'manifest_sharing_groups');

        if ($canonicalGroups === null) {
            return;
        }

        if (! is_array($canonicalGroups)) {
            throw ValidationException::withMessages([
                'manifest_sharing_groups' => 'The manifest_sharing_groups field must be an array when provided.',
            ]);
        }

        $canonicalMembers = [];

        foreach (array_values($canonicalGroups) as $groupIndex => $group) {
            if (! is_array($group)) {
                continue;
            }

            $groupSortOrder = isset($group['sort_order']) ? (int) $group['sort_order'] : ($groupIndex + 1);
            $groupId = isset($group['id']) ? (int) $group['id'] : null;
            $groupKey = $groupId
                ? 'group-'.$groupId
                : 'group-canonical-'.($groupIndex + 1);
            $groupMembers = isset($group['members']) && is_array($group['members'])
                ? array_values($group['members'])
                : [];

            foreach ($groupMembers as $memberIndex => $member) {
                if (! is_array($member)) {
                    continue;
                }

                $patch = isset($member['patch']) && is_array($member['patch'])
                    ? $member['patch']
                    : [];

                $member = [
                    'id' => isset($member['id']) ? (int) $member['id'] : null,
                    'customer_confirmation_member_id' => isset($member['customer_confirmation_member_id'])
                        ? (int) $member['customer_confirmation_member_id']
                        : null,
                    'package_official_id' => isset($member['package_official_id'])
                        ? (int) $member['package_official_id']
                        : null,
                    'relationship' => $member['relationship'] ?? $member['role'] ?? null,
                    'sharing_plan' => $member['sharing_plan'] ?? null,
                    'sort_order' => isset($member['sort_order']) ? (int) $member['sort_order'] : ($memberIndex + 1),
                    'group_sort_order' => $groupSortOrder,
                    'sharing_group_key' => $groupKey,
                    'manifest_sharing_group_id' => $groupId,
                    'sharing_group_id' => $groupId,
                    'group_relationship' => $group['group_relationship'] ?? $group['relation'] ?? $group['relationship'] ?? null,
                    'group_remarks' => $group['remarks'] ?? null,
                    'remarks' => $member['remarks'] ?? null,
                    'status' => $this->normalizeMemberStatus($member['status'] ?? null),
                ];

                foreach ($patch as $key => $value) {
                    $member[(string) $key] = $value;
                }

                $canonicalMembers[] = $member;
            }
        }

        $legacyMembers = $this->flattenGroupedRows(Arr::get($payload, 'members', []));
        $mergedMembers = [];

        foreach ($legacyMembers as $member) {
            $mergedMembers[$this->memberMergeKey($member)] = $member;
        }

        foreach ($canonicalMembers as $member) {
            $key = $this->memberMergeKey($member);

            if (isset($mergedMembers[$key]) && is_array($mergedMembers[$key])) {
                $mergedMembers[$key] = array_replace($mergedMembers[$key], $member);

                continue;
            }

            $mergedMembers[$key] = $member;
        }

        $payload['members'] = array_values($mergedMembers);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyCanonicalRoomLists(array &$payload): void
    {
        $canonicalRooms = Arr::get($payload, 'manifest_rooms');

        if ($canonicalRooms === null) {
            return;
        }

        if (! is_array($canonicalRooms)) {
            throw ValidationException::withMessages([
                'manifest_rooms' => 'The manifest_rooms field must be an array when provided.',
            ]);
        }

        $roomLists = [];

        foreach (array_values($canonicalRooms) as $roomIndex => $room) {
            if (! is_array($room)) {
                continue;
            }

            $location = isset($room['location']) && is_string($room['location'])
                ? strtolower(trim($room['location']))
                : '';

            $locationKey = $location !== '' ? $location : 'unknown';
            $roomId = isset($room['id']) ? (int) $room['id'] : null;
            $groupKey = $roomId
                ? 'room-'.$roomId
                : 'room-canonical-'.($roomIndex + 1);

            $members = isset($room['members']) && is_array($room['members'])
                ? array_values($room['members'])
                : [];

            foreach ($members as $memberIndex => $member) {
                if (! is_array($member)) {
                    continue;
                }

                $roomLists[$locationKey][] = [
                    'manifest_member_id' => isset($member['manifest_member_id'])
                        ? (int) $member['manifest_member_id']
                        : (isset($member['id']) ? (int) $member['id'] : null),
                    'id' => isset($member['id']) ? (int) $member['id'] : null,
                    'customer_confirmation_member_id' => isset($member['customer_confirmation_member_id'])
                        ? (int) $member['customer_confirmation_member_id']
                        : null,
                    'package_official_id' => isset($member['package_official_id'])
                        ? (int) $member['package_official_id']
                        : null,
                    'sort_order' => isset($member['sort_order']) ? (int) $member['sort_order'] : ($memberIndex + 1),
                    'sharing_group_key' => $groupKey,
                    'sharing_plan' => $member['sharing_plan'] ?? ($room['sharing_plan'] ?? null),
                    'room_relationship' => $room['group_relationship'] ?? $room['relationship'] ?? null,
                    'room_label' => $room['room_label'] ?? null,
                    'room_number' => $room['room_number'] ?? null,
                    'room_type' => $room['room_type'] ?? null,
                    'bed_type' => $room['bed_type'] ?? null,
                    'number_of_beds_checked' => (bool) ($room['number_of_beds_checked'] ?? false),
                    'meal' => $room['meal'] ?? null,
                    'room_remarks' => $room['remarks'] ?? null,
                    'remarks' => $member['remarks'] ?? null,
                ];
            }
        }

        if ($roomLists !== []) {
            $payload['roomLists'] = $roomLists;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeSharingGroupsSectionPayload(array $payload, int $manifestId): array
    {
        if (! array_key_exists('manifest_sharing_groups', $payload) && ! array_key_exists('members', $payload)) {
            throw ValidationException::withMessages([
                'manifest_sharing_groups' => 'The manifest_sharing_groups section is required.',
            ]);
        }

        $this->applyCanonicalMembers($payload);
        $payload['members'] = array_map(function (array $member): array {
            $member['room_type'] = $this->normalizeRoomType($member['room_type'] ?? null);
            $member['bed_type'] = $this->normalizeBedType($member['bed_type'] ?? null);
            $member['status'] = $this->normalizeMemberStatus($member['status'] ?? null);

            return $member;
        }, $this->flattenGroupedRows(Arr::get($payload, 'members', [])));
        $payload['id'] = $manifestId;

        return [
            'id' => $payload['id'],
            'members' => $payload['members'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeRoomsSectionPayload(array $payload, int $manifestId): array
    {
        if (! array_key_exists('manifest_rooms', $payload) && ! array_key_exists('roomLists', $payload)) {
            throw ValidationException::withMessages([
                'manifest_rooms' => 'The manifest_rooms section is required.',
            ]);
        }

        $this->applyCanonicalRoomLists($payload);
        $roomLists = Arr::get($payload, 'roomLists', []);

        if (! is_array($roomLists)) {
            $roomLists = [];
        }

        $roomLists = $this->normalizeRoomListsForUi($roomLists);
        $membersContext = Arr::get($payload, 'members', []);

        if (! is_array($membersContext) || $membersContext === []) {
            $membersContext = app(ManifestService::class)->getForEditShow($manifestId)['members'] ?? [];
        }

        $rooms = $this->normalizeRoomRowsFromLists(
            $roomLists,
            $this->flattenGroupedRows($membersContext),
        );

        return [
            'id' => $manifestId,
            'rooms' => $rooms,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeDocumentsSectionPayload(array $payload, int $manifestId): array
    {
        return [
            'id' => $manifestId,
            'documents' => $this->normalizeManifestDocuments(Arr::get($payload, 'documents', [])),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeReceiptDocumentsSectionPayload(array $payload, int $manifestId): array
    {
        $sectionPayload = Arr::get($payload, 'manifest_member_receipts');
        $normalized = [];

        $manifestMemberIds = ManifestMember::query()
            ->where('manifest_id', $manifestId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $manifestMemberIdSet = array_fill_keys($manifestMemberIds, true);
        $confirmationMemberIds = ManifestMember::query()
            ->where('manifest_id', $manifestId)
            ->whereNotNull('customer_confirmation_member_id')
            ->pluck('customer_confirmation_member_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $confirmationMemberIdSet = array_fill_keys($confirmationMemberIds, true);

        if (is_array($sectionPayload) && array_is_list($sectionPayload)) {
            foreach ($sectionPayload as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $resolvedManifestMemberId = isset($item['manifest_member_id'])
                    ? (int) $item['manifest_member_id']
                    : 0;
                $resolvedConfirmationMemberId = isset($item['customer_confirmation_member_id'])
                    ? (int) $item['customer_confirmation_member_id']
                    : 0;
                $entries = isset($item['receipt_documents']) && is_array($item['receipt_documents'])
                    ? $item['receipt_documents']
                    : [];

                $normalized[] = [
                    'manifest_member_id' => isset($manifestMemberIdSet[$resolvedManifestMemberId])
                        ? $resolvedManifestMemberId
                        : null,
                    'customer_confirmation_member_id' => isset($confirmationMemberIdSet[$resolvedConfirmationMemberId])
                        ? $resolvedConfirmationMemberId
                        : null,
                    'receipt_documents' => $this->normalizeReceiptDocumentEntries($entries),
                ];
            }
        }

        if (is_array($sectionPayload) && ! array_is_list($sectionPayload)) {
            foreach ($sectionPayload as $key => $entries) {
                if (! is_array($entries)) {
                    continue;
                }

                $resolvedId = is_numeric($key) ? (int) $key : 0;

                $normalized[] = [
                    'manifest_member_id' => isset($manifestMemberIdSet[$resolvedId]) ? $resolvedId : null,
                    'customer_confirmation_member_id' => isset($manifestMemberIdSet[$resolvedId])
                        ? null
                        : (isset($confirmationMemberIdSet[$resolvedId]) ? $resolvedId : null),
                    'receipt_documents' => $this->normalizeReceiptDocumentEntries($entries),
                ];
            }
        }

        return [
            'id' => $manifestId,
            'manifest_member_receipts' => $normalized,
        ];
    }

    /**
     * @param  array<string, array<int, mixed>>  $allRules
     * @param  array<int, string>  $prefixes
     * @return array<string, array<int, mixed>>
     */
    private function extractRulesByPrefix(array $allRules, array $prefixes): array
    {
        return collect($allRules)
            ->filter(function (array $ruleSet, string $key) use ($prefixes): bool {
                foreach ($prefixes as $prefix) {
                    if ($key === $prefix || str_starts_with($key, $prefix.'.')) {
                        return true;
                    }
                }

                return false;
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $member
     */
    private function memberMergeKey(array $member): string
    {
        if (isset($member['id']) && (int) $member['id'] > 0) {
            return 'id:'.(int) $member['id'];
        }

        if (isset($member['customer_confirmation_member_id']) && (int) $member['customer_confirmation_member_id'] > 0) {
            return 'member:'.(int) $member['customer_confirmation_member_id'];
        }

        if (isset($member['package_official_id']) && (int) $member['package_official_id'] > 0) {
            return 'official:'.(int) $member['package_official_id'];
        }

        return 'fallback:'.sha1(json_encode($member));
    }

    private function normalizeMemberStatus(mixed $status): ?string
    {
        if (! is_string($status)) {
            return null;
        }

        $normalized = Str::lower(trim($status));

        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            'pending', 'pending_payment' => 'pending_payment',
            'deposit', 'partial', 'partially_paid' => 'partially_paid',
            'paid', 'full', 'full_payment', 'fully_paid' => 'fully_paid',
            'cancelled' => 'cancelled',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $roomLists
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function normalizeRoomListsForUi(array $roomLists): array
    {
        $normalized = [];

        foreach ($roomLists as $accommodationKey => $rows) {
            $flatRows = $this->flattenGroupedRows($rows);

            $normalized[(string) $accommodationKey] = array_map(
                function (array $row, int $index): array {
                    $row['room_type'] = $this->normalizeRoomType($row['room_type'] ?? null) ?? ($row['room_type'] ?? null);
                    $row['bed_type'] = $this->normalizeBedType($row['bed_type'] ?? null) ?? ($row['bed_type'] ?? null);
                    $row['room_number'] = $row['room_number'] ?? null;
                    $row['sort_order'] = (int) ($row['sort_order'] ?? $row['sn'] ?? ($index + 1));
                    $row['sn'] = $row['sort_order'];
                    $row['number_of_beds_checked'] = (bool) ($row['number_of_beds_checked'] ?? false);

                    return $row;
                },
                $flatRows,
                array_keys($flatRows),
            );
        }

        return $normalized;
    }

    /**
     * Flatten grouped payload sections: Record<number, Row[]> -> Row[].
     *
     * @return array<int, array<string, mixed>>
     */
    private function flattenGroupedRows(mixed $rows): array
    {
        if (! is_array($rows) || $rows === []) {
            return [];
        }

        $first = reset($rows);

        if (array_is_list($rows) && is_array($first) && ! array_is_list($first)) {
            return array_values(array_filter($rows, fn ($row) => is_array($row)));
        }

        $flattened = [];

        foreach ($rows as $groupRows) {
            if (! is_array($groupRows)) {
                continue;
            }

            foreach ($groupRows as $groupRow) {
                if (is_array($groupRow)) {
                    $flattened[] = $groupRow;
                }
            }
        }

        return $flattened;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRoomRowsFromLists(array $roomLists, array $members = []): array
    {
        $normalizedRooms = [];

        $memberByManifestId = [];
        $memberByConfirmationMemberId = [];
        $memberByPackageOfficialId = [];
        foreach ($members as $member) {
            if (! is_array($member)) {
                continue;
            }

            $manifestMemberId = isset($member['id']) ? (int) $member['id'] : 0;
            if ($manifestMemberId > 0) {
                $memberByManifestId[$manifestMemberId] = $member;
            }

            $confirmationMemberId = isset($member['customer_confirmation_member_id'])
                ? (int) $member['customer_confirmation_member_id']
                : 0;
            if ($confirmationMemberId > 0 && $manifestMemberId > 0) {
                $memberByConfirmationMemberId[$confirmationMemberId] = $member;
            }

            $packageOfficialId = isset($member['package_official_id'])
                ? (int) $member['package_official_id']
                : 0;
            if ($packageOfficialId > 0 && $manifestMemberId > 0) {
                $memberByPackageOfficialId[$packageOfficialId] = $member;
            }
        }

        $memberIds = [];

        foreach ($roomLists as $rows) {
            $flatRows = $this->flattenGroupedRows($rows);

            foreach ($flatRows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $memberId = isset($row['customer_confirmation_member_id'])
                    ? (int) $row['customer_confirmation_member_id']
                    : 0;

                if ($memberId <= 0 && isset($row['manifest_member_id'])) {
                    $manifestMemberId = isset($row['manifest_member_id'])
                        ? (int) $row['manifest_member_id']
                        : 0;
                    $resolvedMember = $memberByManifestId[$manifestMemberId] ?? null;

                    if (is_array($resolvedMember)) {
                        $memberId = isset($resolvedMember['customer_confirmation_member_id'])
                            ? (int) $resolvedMember['customer_confirmation_member_id']
                            : 0;
                    }
                }

                if ($memberId > 0) {
                    $memberIds[] = $memberId;
                }
            }
        }

        $memberConfirmationMap = [];
        if ($memberIds !== []) {
            $memberConfirmationMap = CustomerConfirmationMember::query()
                ->whereIn('id', array_values(array_unique($memberIds)))
                ->pluck('customer_confirmation_id', 'id')
                ->mapWithKeys(fn ($confirmationId, $memberId) => [(int) $memberId => (int) $confirmationId])
                ->all();
        }

        foreach ($roomLists as $location => $rows) {
            $flatRows = $this->flattenGroupedRows($rows);
            if ($flatRows === []) {
                continue;
            }

            $grouped = [];
            $groupSizes = [];
            $groupBuckets = [];
            $groupCounters = [];

            foreach ($flatRows as $rowIndex => $row) {
                $groupKey = isset($row['sharing_group_key']) && is_string($row['sharing_group_key'])
                    ? trim($row['sharing_group_key'])
                    : '';

                $sharingPlan = isset($row['sharing_plan']) && is_string($row['sharing_plan'])
                    ? strtolower(trim($row['sharing_plan']))
                    : '';

                $manifestMemberId = isset($row['manifest_member_id'])
                    ? (int) $row['manifest_member_id']
                    : (isset($row['id']) ? (int) $row['id'] : 0);

                if ($manifestMemberId > 0 && ! isset($memberByManifestId[$manifestMemberId])) {
                    $manifestMemberId = 0;
                }

                $resolvedMember = $manifestMemberId > 0
                    ? ($memberByManifestId[$manifestMemberId] ?? null)
                    : null;

                if (! is_array($resolvedMember)) {
                    $fallbackConfirmationMemberId = isset($row['customer_confirmation_member_id'])
                        ? (int) $row['customer_confirmation_member_id']
                        : 0;
                    $fallbackPackageOfficialId = isset($row['package_official_id'])
                        ? (int) $row['package_official_id']
                        : 0;

                    if ($fallbackConfirmationMemberId > 0) {
                        $resolvedMember = $memberByConfirmationMemberId[$fallbackConfirmationMemberId] ?? null;
                    }

                    if (! is_array($resolvedMember) && $fallbackPackageOfficialId > 0) {
                        $resolvedMember = $memberByPackageOfficialId[$fallbackPackageOfficialId] ?? null;
                    }

                    if (is_array($resolvedMember) && isset($resolvedMember['id'])) {
                        $manifestMemberId = (int) $resolvedMember['id'];
                    }
                }

                if ($sharingPlan === '' && is_array($resolvedMember) && isset($resolvedMember['sharing_plan']) && is_string($resolvedMember['sharing_plan'])) {
                    $sharingPlan = strtolower(trim($resolvedMember['sharing_plan']));
                }

                $roomType = $this->normalizeRoomType($row['room_type'] ?? null);

                if ($roomType === null) {
                    $roomType = $this->roomTypeFromSharingPlan($sharingPlan !== '' ? $sharingPlan : null) ?? 'single';
                }

                $confirmationMemberId = isset($row['customer_confirmation_member_id'])
                    ? (int) $row['customer_confirmation_member_id']
                    : 0;

                if ($confirmationMemberId <= 0 && is_array($resolvedMember)) {
                    $confirmationMemberId = isset($resolvedMember['customer_confirmation_member_id'])
                        ? (int) $resolvedMember['customer_confirmation_member_id']
                        : 0;
                }

                $confirmationId = isset($row['customer_confirmation_id'])
                    ? (int) $row['customer_confirmation_id']
                    : (is_array($resolvedMember) && isset($resolvedMember['customer_confirmation_id'])
                        ? (int) $resolvedMember['customer_confirmation_id']
                        : 0);

                if ($confirmationId <= 0 && $confirmationMemberId > 0) {
                    $confirmationId = (int) ($memberConfirmationMap[$confirmationMemberId] ?? 0);
                }

                $isOfficial = ! empty($row['package_official_id'])
                    || ! empty($row['is_official'])
                    || (is_array($resolvedMember) && ! empty($resolvedMember['package_official_id']));

                $capacity = $this->capacityFromRoomType($roomType);
                $bucketKey = $confirmationId > 0
                    ? $confirmationId.'|'.$roomType.'|'.($sharingPlan !== '' ? $sharingPlan : 'single').'|'.($isOfficial ? 'official' : 'member')
                    : 0;

                if ($bucketKey === 0) {
                    $bucketKey = null;
                }

                $isExplicitKey = $groupKey !== '' && ! str_starts_with($groupKey, 'solo-');

                if (! $isExplicitKey && $bucketKey !== null) {
                    $candidateKeys = $groupBuckets[$bucketKey] ?? [];
                    $selectedKey = null;

                    foreach ($candidateKeys as $candidateKey) {
                        if (($groupSizes[$candidateKey] ?? 0) < $capacity) {
                            $selectedKey = $candidateKey;
                            break;
                        }
                    }

                    if ($selectedKey === null) {
                        $groupCounters[$bucketKey] = ($groupCounters[$bucketKey] ?? 0) + 1;
                        $selectedKey = 'room-auto-'.$bucketKey.'-'.$groupCounters[$bucketKey];
                        $groupBuckets[$bucketKey][] = $selectedKey;
                    }

                    $groupKey = $selectedKey;
                }

                if ($groupKey === '') {
                    $memberId = $confirmationMemberId > 0
                        ? $confirmationMemberId
                        : null;
                    $customerId = isset($row['customer_id'])
                        ? (int) $row['customer_id']
                        : null;

                    $groupKey = 'solo-'.($memberId ?: $customerId ?: ($rowIndex + 1));
                }

                if ($bucketKey !== null && ! in_array($groupKey, $groupBuckets[$bucketKey] ?? [], true)) {
                    $groupBuckets[$bucketKey][] = $groupKey;
                }

                $groupSizes[$groupKey] = ($groupSizes[$groupKey] ?? 0) + 1;

                if ($manifestMemberId > 0) {
                    $row['manifest_member_id'] = $manifestMemberId;
                }

                if ($confirmationMemberId > 0 && empty($row['customer_confirmation_member_id'])) {
                    $row['customer_confirmation_member_id'] = $confirmationMemberId;
                }

                if ($confirmationId > 0 && empty($row['customer_confirmation_id'])) {
                    $row['customer_confirmation_id'] = $confirmationId;
                }

                if ($sharingPlan !== '' && empty($row['sharing_plan'])) {
                    $row['sharing_plan'] = $sharingPlan;
                }

                $row['room_type'] = $roomType;

                $grouped[$groupKey][] = $row;
            }

            $roomGroupSortOrder = 1;

            foreach ($grouped as $members) {
                $first = $members[0];

                $normalizedRooms[] = [
                    'sort_order' => $roomGroupSortOrder,
                    'location' => is_string($location) ? $location : null,
                    'group_relationship' => $first['room_relationship'] ?? null,
                    'room_label' => $first['room_label'] ?? null,
                    'room_number' => $first['room_number'] ?? null,
                    'room_type' => $this->normalizeRoomType($first['room_type'] ?? null),
                    'bed_type' => $this->normalizeBedType($first['bed_type'] ?? null),
                    'capacity' => count($members),
                    'meal' => $first['meal'] ?? null,
                    'number_of_beds_checked' => (bool) ($first['number_of_beds_checked'] ?? false),
                    'remarks' => $first['room_remarks'] ?? null,
                    'status' => 'pending',
                    'members' => array_values(array_map(function (array $member, int $index): array {
                        $manifestMemberId = isset($member['manifest_member_id'])
                            ? (int) $member['manifest_member_id']
                            : (isset($member['id']) ? (int) $member['id'] : null);

                        return [
                            'manifest_member_id' => $manifestMemberId,
                            'id' => isset($member['id']) ? (int) $member['id'] : null,
                            'customer_confirmation_member_id' => isset($member['customer_confirmation_member_id'])
                                ? (int) $member['customer_confirmation_member_id']
                                : null,
                            'package_official_id' => isset($member['package_official_id'])
                                ? (int) $member['package_official_id']
                                : null,
                            'sharing_plan' => isset($member['sharing_plan']) && is_string($member['sharing_plan'])
                                ? strtolower(trim($member['sharing_plan']))
                                : null,
                            'sort_order' => (int) ($member['sort_order'] ?? $member['sn'] ?? ($index + 1)),
                            'remarks' => $member['remarks'] ?? null,
                        ];
                    }, $members, array_keys($members))),
                ];

                $roomGroupSortOrder++;
            }
        }

        return $normalizedRooms;
    }

    /**
     * @param  array<int, mixed>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function normalizeReceiptDocumentEntries(array $entries): array
    {
        $normalized = $this->normalizeDocumentEntries($entries);

        $documentIds = collect($normalized)
            ->pluck('id')
            ->filter(fn ($id) => is_int($id) && $id > 0)
            ->values()
            ->all();

        if ($documentIds === []) {
            return $normalized;
        }

        $existingDocumentIdSet = ModelFile::query()
            ->whereIn('id', $documentIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $existingDocumentIdSet = array_fill_keys($existingDocumentIdSet, true);

        return array_map(function (array $entry) use ($existingDocumentIdSet): array {
            $id = isset($entry['id']) ? (int) $entry['id'] : null;

            if ($id && ! isset($existingDocumentIdSet[$id])) {
                $entry['id'] = null;
            }

            return $entry;
        }, $normalized);
    }

    private function capacityFromRoomType(?string $roomType): int
    {
        return match (strtolower((string) $roomType)) {
            'quad' => 4,
            'triple' => 3,
            'double', 'twin' => 2,
            default => 1,
        };
    }

    private function roomTypeFromSharingPlan(?string $sharingPlan): ?string
    {
        return match (strtolower((string) $sharingPlan)) {
            'quad' => 'quad',
            'triple' => 'triple',
            'double' => 'double',
            'child_with_bed', 'child_no_bed', 'infant' => 'single',
            'single' => 'single',
            default => null,
        };
    }

    private function normalizeRoomType(mixed $roomType): ?string
    {
        if (! is_string($roomType) || trim($roomType) === '') {
            return null;
        }

        $mapped = strtolower(trim($roomType));

        return match ($mapped) {
            'single' => 'single',
            'twin' => 'twin',
            'double' => 'double',
            'triple' => 'triple',
            'quad' => 'quad',
            default => null,
        };
    }

    private function normalizeBedType(mixed $bedType): ?string
    {
        if (! is_string($bedType) || trim($bedType) === '') {
            return null;
        }

        $mapped = strtolower(trim($bedType));

        return match ($mapped) {
            'single' => 'single',
            'king' => 'king',
            'queen' => 'queen',
            default => null,
        };
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function normalizeManifestDocuments(mixed $documents): array
    {
        $allowedFields = ['train_tickets', 'flight_tickets', 'visa', 'hotel', 'passport', 'photo'];

        if (! is_array($documents)) {
            return collect($allowedFields)
                ->mapWithKeys(fn (string $field) => [$field => []])
                ->all();
        }

        return collect($allowedFields)
            ->mapWithKeys(function (string $field) use ($documents): array {
                $entries = $documents[$field] ?? [];

                return [
                    $field => $this->normalizeDocumentEntries(is_array($entries) ? $entries : []),
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, mixed>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDocumentEntries(array $entries): array
    {
        $normalized = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $id = isset($entry['id']) ? (int) $entry['id'] : null;
            $file = $entry['file'] ?? null;
            $fileName = isset($entry['file_name']) && is_string($entry['file_name'])
                ? trim($entry['file_name'])
                : null;
            $filePath = isset($entry['file_path']) && is_string($entry['file_path'])
                ? trim($entry['file_path'])
                : null;
            $removed = (bool) ($entry['removed'] ?? false);

            if (! $removed && ! $id && ! $file && ! $filePath) {
                continue;
            }

            $normalized[] = [
                'id' => $id,
                'file' => $file,
                'file_name' => $fileName !== '' ? $fileName : null,
                'file_path' => $filePath !== '' ? $filePath : null,
                'removed' => $removed,
            ];
        }

        return $normalized;
    }

    private function resolveScopedManifest(int $manifestId): Manifest
    {
        $query = Manifest::query();

        $user = DataScope::user();

        if ($user && DataScope::shouldScopePackageAndManifestCountry($user)) {
            $countryIds = DataScope::scopedCountryIds($user);

            if (! empty($countryIds)) {
                $query->whereHas('package', function (Builder $packageQuery) use ($countryIds) {
                    $packageQuery->whereIn('country_id', $countryIds);
                });
            }
        }

        return $query->findOrFail($manifestId);
    }

    /**
     * Ensure confirmation members attached as members belong to the same package as manifest.
     *
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException
     */
    private function ensureMemberPackageMatchesManifestPackage(array $validated): void
    {
        $manifestPackageId = isset($validated['package_id']) ? (int) $validated['package_id'] : null;

        if (! $manifestPackageId) {
            return;
        }

        $members = $this->flattenGroupedRows(Arr::get($validated, 'members', []));
        $memberIds = collect($members)
            ->pluck('customer_confirmation_member_id')
            ->filter()
            ->map(fn (mixed $memberId) => (int) $memberId)
            ->unique()
            ->values();

        if ($memberIds->isEmpty()) {
            return;
        }

        $package = Package::query()->find($manifestPackageId);
        if ($package) {
            $packageSeatService = app(PackageSeatService::class);

            if ($packageSeatService->isBlockedForMemberIntake((string) $package->status)) {
                $manifestId = isset($validated['id']) ? (int) $validated['id'] : 0;

                $existingLinkedMemberIds = $manifestId > 0
                    ? ManifestMember::query()
                        ->where('manifest_id', $manifestId)
                        ->whereIn('customer_confirmation_member_id', $memberIds->all())
                        ->pluck('customer_confirmation_member_id')
                        ->map(fn (mixed $memberId) => (int) $memberId)
                        ->filter(fn (int $memberId) => $memberId > 0)
                        ->unique()
                        ->values()
                    : collect();

                $newLinkedMemberIds = $memberIds->diff($existingLinkedMemberIds);

                if ($newLinkedMemberIds->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'members' => 'Selected package is '.strtolower((string) $package->status).' and cannot accept new members.',
                    ]);
                }
            }
        }

        $hasMismatch = CustomerConfirmationMember::query()
            ->whereIn('id', $memberIds->all())
            ->whereHas('confirmation', function ($query) use ($manifestPackageId) {
                $query->where('package_id', '!=', $manifestPackageId);
            })
            ->exists();

        if ($hasMismatch) {
            throw ValidationException::withMessages([
                'members' => 'Customer confirmation package must match the selected manifest package.',
            ]);
        }
    }
}
