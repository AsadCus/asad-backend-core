<?php

namespace App\Http\Controllers;

use App\Models\CustomerConfirmationMember;
use App\Models\ManifestMember;
use App\Rules\ManifestRule;
use App\Services\CustomerConfirmationService;
use App\Services\ManifestService;
use App\Services\PackageService;
use App\Services\Report\ReportTemplateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ManifestController extends Controller
{
    public function __construct(
        protected ManifestService $manifestService,
        protected ManifestRule $manifestRule,
        protected PackageService $packageService,
        protected CustomerConfirmationService $customerConfirmationService,
        protected ReportTemplateService $reportTemplateService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): \Inertia\Response
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
        $normalizedPayload = $this->normalizeManifestPayload($requestPayload);
        $validated = validator(
            $normalizedPayload,
            $this->manifestRule->rules($manifestId > 0 ? $manifestId : null),
        )->validate();
        $validated['roomLists'] = $normalizedPayload['roomLists'] ?? [];
        $this->ensureTravelerPackageMatchesManifestPackage($validated);

        if ($manifestId > 0) {
            $this->manifestService->update($validated, $manifestId);

            return redirect()->route('manifests.index')
                ->with('success', 'Manifest updated successfully.');
        }

        $this->manifestService->store($validated);

        return redirect()->route('manifests.index')
            ->with('success', 'Manifest created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): \Inertia\Response
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
    public function edit(string $id): \Inertia\Response
    {
        $manifest = $this->manifestService->getForEditShow($id);
        $dataPackage = $this->packageService->getForFilter();

        return Inertia::render('manifests/edit', [
            'data' => $manifest,
            'dataPackage' => $dataPackage,
        ]);
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

    /**
     * Add a payment to a manifest.
     */
    public function addPayment(Request $request, string $manifestId): JsonResponse
    {
        $validated = $request->validate($this->manifestRule->paymentRules());
        $payment = $this->manifestService->addPayment((int) $manifestId, $validated);

        return response()->json(['payment' => $payment], 201);
    }

    /**
     * Update a payment.
     */
    public function updatePayment(Request $request, string $paymentId): JsonResponse
    {
        $validated = $request->validate($this->manifestRule->paymentRules());
        $payment = $this->manifestService->updatePayment((int) $paymentId, $validated);

        return response()->json(['payment' => $payment]);
    }

    /**
     * Delete a payment.
     */
    public function deletePayment(string $paymentId): JsonResponse
    {
        $this->manifestService->deletePayment((int) $paymentId);

        return response()->json(['message' => 'Payment deleted successfully.']);
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
            $snapshot = $request->input('snapshot');

            if (is_string($snapshot) && $snapshot !== '') {
                $decodedSnapshot = json_decode($snapshot, true);

                if (is_array($decodedSnapshot)) {
                    if (isset($decodedSnapshot['travelers']) && is_array($decodedSnapshot['travelers'])) {
                        $manifest['travelers'] = $decodedSnapshot['travelers'];
                    }

                    if (isset($decodedSnapshot['manifest_number'])) {
                        $manifest['manifest_number'] = $decodedSnapshot['manifest_number'];
                    }

                    if (isset($decodedSnapshot['package_name'])) {
                        $manifest['package_name'] = $decodedSnapshot['package_name'];
                    }

                    if (isset($decodedSnapshot['package_number'])) {
                        $manifest['package_number'] = $decodedSnapshot['package_number'];
                    }

                    if (isset($decodedSnapshot['departure_date'])) {
                        $manifest['departure_date'] = $decodedSnapshot['departure_date'];
                    }

                    if (isset($decodedSnapshot['return_date'])) {
                        $manifest['return_date'] = $decodedSnapshot['return_date'];
                    }

                    if (isset($decodedSnapshot['package_accommodations']) && is_array($decodedSnapshot['package_accommodations'])) {
                        $manifest['package_accommodations'] = $decodedSnapshot['package_accommodations'];
                    }
                }
            }

            $reportData = $this->reportTemplateService->build('manifest', [
                'manifest' => $manifest,
            ]);

            $html = view('manifests.namelist-course-items-report-content', [
                'manifest' => $manifest,
                'branding' => $reportData['branding'],
                'is_pdf' => true,
            ])->render();

            $manifestNumber = trim((string) ($manifest['manifest_number'] ?? 'Manifest'));
            $fileName = 'Manifest Namelist Course & Collection Items - '.$manifestNumber.'.pdf';

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
            $snapshot = $request->input('snapshot');

            if (is_string($snapshot) && $snapshot !== '') {
                $decodedSnapshot = json_decode($snapshot, true);

                if (is_array($decodedSnapshot)) {
                    if (isset($decodedSnapshot['travelers']) && is_array($decodedSnapshot['travelers'])) {
                        $manifest['travelers'] = $decodedSnapshot['travelers'];
                    }

                    if (isset($decodedSnapshot['manifest_number'])) {
                        $manifest['manifest_number'] = $decodedSnapshot['manifest_number'];
                    }

                    if (isset($decodedSnapshot['package_name'])) {
                        $manifest['package_name'] = $decodedSnapshot['package_name'];
                    }

                    if (isset($decodedSnapshot['departure_date'])) {
                        $manifest['departure_date'] = $decodedSnapshot['departure_date'];
                    }

                    if (isset($decodedSnapshot['in_charge_official_name'])) {
                        $manifest['in_charge_official_name'] = $decodedSnapshot['in_charge_official_name'];
                    }

                    if (isset($decodedSnapshot['in_charge_official_contact_number'])) {
                        $manifest['in_charge_official_contact_number'] = $decodedSnapshot['in_charge_official_contact_number'];
                    }
                }
            }

            $reportData = $this->reportTemplateService->build('manifest', [
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

            $snapshot = $request->input('snapshot');

            if (is_string($snapshot) && $snapshot !== '') {
                $decodedSnapshot = json_decode($snapshot, true);

                if (is_array($decodedSnapshot)) {
                    if (isset($decodedSnapshot['room_check_rows']) && is_array($decodedSnapshot['room_check_rows'])) {
                        $manifest['room_check_rows'] = $decodedSnapshot['room_check_rows'];
                    }

                    if (isset($decodedSnapshot['room_check_location_label'])) {
                        $manifest['room_check_location_label'] = $decodedSnapshot['room_check_location_label'];
                    }

                    if (isset($decodedSnapshot['manifest_number'])) {
                        $manifest['manifest_number'] = $decodedSnapshot['manifest_number'];
                    }

                    if (isset($decodedSnapshot['package_name'])) {
                        $manifest['package_name'] = $decodedSnapshot['package_name'];
                    }

                    if (isset($decodedSnapshot['package_number'])) {
                        $manifest['package_number'] = $decodedSnapshot['package_number'];
                    }

                    if (isset($decodedSnapshot['departure_date'])) {
                        $manifest['departure_date'] = $decodedSnapshot['departure_date'];
                    }

                    if (isset($decodedSnapshot['return_date'])) {
                        $manifest['return_date'] = $decodedSnapshot['return_date'];
                    }

                    if (isset($decodedSnapshot['package_accommodations']) && is_array($decodedSnapshot['package_accommodations'])) {
                        $manifest['package_accommodations'] = $decodedSnapshot['package_accommodations'];
                    }
                }
            }

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

            if (! isset($manifest['room_check_rows']) || ! is_array($manifest['room_check_rows'])) {
                $manifest['room_check_rows'] = $roomRows;
            }

            $reportData = $this->reportTemplateService->build('manifest', [
                'manifest' => $manifest,
            ]);

            $html = view('manifests.room-check-report-content', [
                'manifest' => $manifest,
                'branding' => $reportData['branding'],
                'is_pdf' => true,
            ])->render();

            $manifestNumber = trim((string) ($manifest['manifest_number'] ?? 'Manifest'));
            $fileName = 'Manifest Room Check - '.$manifestNumber.'.pdf';

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
     * Move one manifest traveler to a new holding confirmation and cancel existing assignment.
     */
    public function moveTravelerToHolding(Request $request, string $manifestId, string $travelerId): JsonResponse
    {
        $validated = $request->validate([
            'target_package_id' => ['nullable', 'integer', 'exists:packages,id'],
        ]);

        $traveler = ManifestMember::query()
            ->where('manifest_id', (int) $manifestId)
            ->where('id', (int) $travelerId)
            ->firstOrFail();

        if (! $traveler->customer_confirmation_member_id) {
            throw ValidationException::withMessages([
                'traveler' => 'Only confirmation-linked travelers can be moved to holding.',
            ]);
        }

        $member = CustomerConfirmationMember::query()
            ->findOrFail((int) $traveler->customer_confirmation_member_id);

        $newConfirmation = $this->customerConfirmationService->moveMembersToHolding(
            (int) $member->customer_confirmation_id,
            [(int) $member->id],
            $validated['target_package_id'] ?? null,
            (int) $manifestId,
        );

        return response()->json([
            'message' => 'Traveler moved to holding confirmation successfully.',
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
        $travelers = array_map(function (array $traveler): array {
            $traveler['room_type'] = $this->normalizeRoomType($traveler['room_type'] ?? null);
            $traveler['bed_type'] = $this->normalizeBedType($traveler['bed_type'] ?? null);
            $traveler['receipt_documents'] = $this->normalizeDocumentEntries(
                is_array($traveler['receipt_documents'] ?? null)
                    ? $traveler['receipt_documents']
                    : [],
            );

            return $traveler;
        }, $this->flattenGroupedRows(Arr::get($payload, 'travelers', [])));
        $payload['travelers'] = $travelers;
        $payload['documents'] = $this->normalizeManifestDocuments(
            Arr::get($payload, 'documents', []),
        );

        $roomLists = Arr::get($payload, 'roomLists', []);

        if (! is_array($roomLists)) {
            $roomLists = [];
        }

        $roomLists = $this->normalizeRoomListsForUi($roomLists);

        $roomRows = $this->normalizeRoomRowsFromLists($roomLists);

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
                    $roomNumber = $row['room_number'] ?? $row['room_no'] ?? null;
                    $row['room_number'] = $roomNumber;
                    $row['room_no'] = $roomNumber;
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
    private function normalizeRoomRowsFromLists(array $roomLists): array
    {
        $normalizedRooms = [];

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

                $confirmationId = isset($row['customer_confirmation_id'])
                    ? (int) $row['customer_confirmation_id']
                    : 0;

                $isOfficial = ! empty($row['package_official_id']) || ! empty($row['is_official']);
                $capacity = $this->capacityFromSharingPlan($sharingPlan !== '' ? $sharingPlan : null);
                $bucketKey = $confirmationId > 0 && $sharingPlan !== ''
                    ? $confirmationId.'|'.$sharingPlan.'|'.($isOfficial ? 'official' : 'member')
                    : null;

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
                    $memberId = isset($row['customer_confirmation_member_id'])
                        ? (int) $row['customer_confirmation_member_id']
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

                $grouped[$groupKey][] = $row;
            }

            $roomGroupSortOrder = 1;

            foreach ($grouped as $members) {
                $first = $members[0];

                $normalizedRooms[] = [
                    'sort_order' => $roomGroupSortOrder,
                    'location' => is_string($location) ? $location : null,
                    'relationship' => $first['room_relationship'] ?? null,
                    'room_label' => $first['room_label'] ?? null,
                    'room_number' => $first['room_number'] ?? $first['room_no'] ?? null,
                    'room_type' => $this->normalizeRoomType($first['room_type'] ?? null),
                    'bed_type' => $this->normalizeBedType($first['bed_type'] ?? null),
                    'sharing_plan' => $first['sharing_plan'] ?? null,
                    'capacity' => count($members),
                    'meal' => $first['meal'] ?? null,
                    'number_of_beds_checked' => (bool) ($first['number_of_beds_checked'] ?? false),
                    'remarks' => $first['room_remarks'] ?? null,
                    'status' => 'pending',
                    'members' => array_values(array_map(function (array $member, int $index): array {
                        $manifestTravelerId = isset($member['manifest_traveler_id'])
                            ? (int) $member['manifest_traveler_id']
                            : (isset($member['id']) ? (int) $member['id'] : null);

                        return [
                            'manifest_traveler_id' => $manifestTravelerId,
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

    private function capacityFromSharingPlan(?string $sharingPlan): int
    {
        return match (strtolower((string) $sharingPlan)) {
            'quad' => 4,
            'triple' => 3,
            'double' => 2,
            default => 1,
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
        $allowedFields = ['flight_tickets', 'visa', 'hotel', 'passport', 'photo'];

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

    /**
     * Ensure confirmation members attached as travelers belong to the same package as manifest.
     *
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException
     */
    private function ensureTravelerPackageMatchesManifestPackage(array $validated): void
    {
        $manifestPackageId = isset($validated['package_id']) ? (int) $validated['package_id'] : null;

        if (! $manifestPackageId) {
            return;
        }

        $travelers = $this->flattenGroupedRows(Arr::get($validated, 'travelers', []));
        $memberIds = collect($travelers)
            ->pluck('customer_confirmation_member_id')
            ->filter()
            ->map(fn (mixed $memberId) => (int) $memberId)
            ->unique()
            ->values();

        if ($memberIds->isEmpty()) {
            return;
        }

        $hasMismatch = CustomerConfirmationMember::query()
            ->whereIn('id', $memberIds->all())
            ->whereHas('confirmation', function ($query) use ($manifestPackageId) {
                $query->where('package_id', '!=', $manifestPackageId);
            })
            ->exists();

        if ($hasMismatch) {
            throw ValidationException::withMessages([
                'travelers' => 'Customer confirmation package must match the selected manifest package.',
            ]);
        }
    }
}
