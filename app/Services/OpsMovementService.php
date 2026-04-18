<?php

namespace App\Services;

use App\Helpers\NumberGenerator;
use App\Models\Manifest;
use App\Models\Package;
use App\Support\DataScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OpsMovementService
{
    /**
     * Get ops movement data aggregated from packages and their manifests.
     * This is a read-only view — no separate table needed.
     */
    public function getForDataTable(array $filters = [])
    {
        $packageQuery = Package::query()
            ->with(['accommodations', 'manifests.members']);

        $this->applyOperationsCountryScope($packageQuery);

        $data = $packageQuery
            ->when($filters['search'] ?? null, function ($q, $value) {
                $q->where(function ($query) use ($value) {
                    $query->where('package_number', 'like', "%{$value}%")
                        ->orWhere('name', 'like', "%{$value}%");
                });
            })
            ->when($filters['status'] ?? null, function ($q, $value) {
                $q->where('status', $value);
            })
            ->orderBy('departure_date', 'desc')
            ->get()
            ->map(function ($package) {
                $totalMembers = $package->manifests->sum(function ($m) {
                    return $m->members->count();
                });

                $primaryManifest = $package->manifests->sortByDesc('id')->first();

                return [
                    'id' => $package->id,
                    'ops_movement_number' => $primaryManifest
                        ? $this->buildOpsMovementNumber($primaryManifest)
                        : null,
                    'package_number' => $package->package_number,
                    'name' => $package->name,
                    'status' => $package->status,
                    'launched' => $package->launched,
                    'departure_date' => $package->departure_date_formatted,
                    'return_date' => $package->return_date_formatted,
                    'total_seats' => $package->total_seats,
                    'seats_left' => $package->seats_left,
                    'visa_type' => $package->visa_type,
                    'vehicle_type' => $package->vehicle_type,
                    'ticket_type' => $package->ticket_type,
                    'total_members' => $totalMembers,
                    'manifests_count' => $package->manifests->count(),
                    'accommodations' => $package->accommodations->map(function ($a) {
                        return [
                            'id' => $a->id,
                            'location' => $a->location,
                            'hotel_name' => $a->hotel_name,
                            'ic' => $a->ic,
                            'type_of_meal' => $a->type_of_meal,
                            'check_in' => $a->check_in_formatted,
                            'check_out' => $a->check_out_formatted,
                        ];
                    })->toArray(),
                ];
            });

        return $data;
    }

    /**
     * Get details of a single package for ops movement view.
     */
    public function getForShow($id): array
    {
        $packageQuery = Package::query()->with([
            'accommodations',
            'flights',
            'trainTickets',
            'officials',
            'rawdahTasreehs',
            'transportationPlans',
            'manifests.members',
            'manifests.rooms.roomMembers.member',
            'manifests.files',
        ]);

        $this->applyOperationsCountryScope($packageQuery);

        $package = $packageQuery->findOrFail($id);

        $manifest = $package->manifests->sortByDesc('id')->first();
        $extension = $manifest?->ops_movement_extension ?? [];
        $flightOpsMap = collect($extension['flights'] ?? [])->keyBy('id');
        $officialOpsMap = collect($extension['officials'] ?? [])
            ->filter(fn ($official) => is_array($official) && isset($official['id']))
            ->keyBy('id');
        $accommodationLocations = $package->accommodations
            ->map(fn ($accommodation) => $this->normalizeNullableString($accommodation->location))
            ->filter(fn ($location) => $location !== null)
            ->unique(fn ($location) => strtolower((string) $location))
            ->values();
        $documents = $this->buildOpsMovementDocumentPayload($manifest);
        $budget = $this->normalizeBudgetPayload($extension['budget'] ?? []);
        $budgetCurrency = $this->normalizeNullableString($extension['budget_currency'] ?? null) ?? 'SAR';
        $nonOfficialMembers = collect($manifest?->members ?? [])
            ->filter(fn ($member) => $member->package_official_id === null && $member->status !== 'cancelled')
            ->values();
        $officialMembers = collect($manifest?->members ?? [])
            ->filter(fn ($member) => $member->package_official_id !== null && $member->status !== 'cancelled')
            ->values();
        $roomCountsByLocation = $this->buildRoomCountsByLocationFromManifest($manifest);
        $departureReferenceDate = $package->departure_date
            ? Carbon::parse($package->departure_date)->startOfDay()
            : null;

        $adultMembers = $nonOfficialMembers->filter(function ($member) use ($departureReferenceDate): bool {
            $age = $this->resolveAge($member->date_of_birth, $departureReferenceDate);

            return $age === null || $age >= 12;
        })->values();
        $childMembers = $nonOfficialMembers->filter(function ($member) use ($departureReferenceDate): bool {
            $age = $this->resolveAge($member->date_of_birth, $departureReferenceDate);

            return $age !== null && $age >= 2 && $age < 12;
        })->values();
        $infantMembers = $nonOfficialMembers->filter(function ($member) use ($departureReferenceDate): bool {
            $age = $this->resolveAge($member->date_of_birth, $departureReferenceDate);

            return $age !== null && $age < 2;
        })->values();
        $sharingPlanCounts = $nonOfficialMembers
            ->map(function ($member): string {
                return $this->normalizeSharingPlan($member->sharing_plan ?? null);
            })
            ->countBy();
        $tourLeaders = $this->resolvePifTourLeaders(
            $extension['pif']['tour_leaders'] ?? [],
            $package->officials,
        );

        return [
            'id' => $package->id,
            'manifest_id' => $manifest?->id,
            'ops_movement_number' => $manifest ? $this->buildOpsMovementNumber($manifest) : null,
            'package_number' => $package->package_number,
            'manifest_number' => $manifest?->manifest_number,
            'name' => $package->name,
            'status' => $package->status,
            'departure_date' => $package->departure_date_formatted,
            'return_date' => $package->return_date_formatted,
            'departure_return_range' => $this->buildDateRangeLabel($package->departure_date, $package->return_date),
            'first_hotel_name' => $package->accommodations->first()?->hotel_name,
            'visa_type' => $package->visa_type,
            'ops_base' => $extension['ops_base'] ?? null,
            'infotech_ref' => $extension['infotech_ref'] ?? null,
            'location' => $extension['location'] ?? null,
            'doa_by' => $extension['doa_by'] ?? data_get($extension, 'flights.0.doa_by'),
            'doa_datetime' => $extension['doa_datetime'] ?? data_get($extension, 'flights.0.doa_datetime'),
            'documents' => $documents,
            'budget' => $budget,
            'budget_currency' => $budgetCurrency,
            'vehicle_type' => $package->vehicle_type,
            'vehicle_driver_name' => $package->vehicle_driver_name,
            'vehicle_driver_contact_number' => $package->vehicle_driver_contact_number,
            'train_description' => $package->train_description,
            'visa_submitted_to_z_umrah' => (bool) ($extension['visa_submitted_to_z_umrah'] ?? false),
            'visa_approved' => (bool) ($extension['visa_approved'] ?? false),
            'mutawwif_name' => $extension['mutawwif_name'] ?? null,
            'passengers' => [
                'adult_total' => $adultMembers->count(),
                'adult_male' => $adultMembers->filter(fn ($member) => strtolower((string) $member->gender) === 'male')->count(),
                'adult_female' => $adultMembers->filter(fn ($member) => strtolower((string) $member->gender) === 'female')->count(),
                'child_total' => $childMembers->count(),
                'child_boy' => $childMembers->filter(fn ($member) => strtolower((string) $member->gender) === 'male')->count(),
                'child_girl' => $childMembers->filter(fn ($member) => strtolower((string) $member->gender) === 'female')->count(),
                'child_with_bed_total' => (int) ($sharingPlanCounts['child_with_bed'] ?? 0),
                'child_no_bed_total' => (int) ($sharingPlanCounts['child_no_bed'] ?? 0),
                'infant_total' => $infantMembers->count(),
                'official_total' => $officialMembers->count(),
                'wheelchair_non_official_total' => $nonOfficialMembers->filter(fn ($member) => $member->is_using_wheelchair === true)->count(),
                'grand_total' => $nonOfficialMembers->count() + $officialMembers->count(),
            ],
            'accommodations' => $package->accommodations->map(function ($accommodation) use ($roomCountsByLocation, $manifest) {
                $locationKey = $this->normalizeLocationKey($accommodation->location);
                $locationRooms = collect($manifest?->rooms ?? [])
                    ->filter(function ($room) use ($locationKey): bool {
                        return $this->normalizeLocationKey($room->location) === $locationKey;
                    });
                $locationMembers = $locationRooms
                    ->flatMap(fn ($room) => collect($room->roomMembers ?? []))
                    ->map(fn ($roomMember) => $roomMember->member)
                    ->filter();

                if ($locationMembers->isEmpty()) {
                    $locationMembers = collect($manifest?->members ?? [])
                        ->filter(fn ($member) => $member->package_official_id === null && $member->status !== 'cancelled');
                }

                $childWithBedCount = $locationMembers->filter(function ($member): bool {
                    return $this->normalizeSharingPlan($member->sharing_plan ?? null) === 'child_with_bed';
                })->count();
                $childNoBedCount = $locationMembers->filter(function ($member): bool {
                    return $this->normalizeSharingPlan($member->sharing_plan ?? null) === 'child_no_bed';
                })->count();
                $infantCount = $locationMembers->filter(function ($member): bool {
                    return $this->normalizeSharingPlan($member->sharing_plan ?? null) === 'infant';
                })->count();

                $singleCount = (int) ($roomCountsByLocation[$locationKey]['single'] ?? 0);
                if ($singleCount <= 0 && $locationRooms->isNotEmpty()) {
                    $singleCount = $locationRooms
                        ->filter(function ($room): bool {
                            return $this->normalizeRoomTypeLabel($room->room_type) === 'single';
                        })
                        ->count();
                }

                return [
                    'id' => $accommodation->id,
                    'location' => $accommodation->location,
                    'hotel_name' => $accommodation->hotel_name,
                    'ic' => $accommodation->ic,
                    'type_of_meal' => $accommodation->type_of_meal,
                    'check_in' => $accommodation->check_in_formatted,
                    'check_out' => $accommodation->check_out_formatted,
                    'nights' => $accommodation->check_in && $accommodation->check_out
                        ? $accommodation->check_in->diffInDays($accommodation->check_out)
                        : 0,
                    'room_counts' => [
                        'single' => $singleCount > 0
                            ? $singleCount
                            : ($locationRooms->isNotEmpty() ? $locationRooms->count() + $childWithBedCount + $childNoBedCount + $infantCount : 0),
                        'double' => $roomCountsByLocation[$locationKey]['double'] ?? 0,
                        'triple' => $roomCountsByLocation[$locationKey]['triple'] ?? 0,
                        'quad' => $roomCountsByLocation[$locationKey]['quad'] ?? 0,
                        'child_with_bed' => $childWithBedCount,
                        'child_no_bed' => $childNoBedCount,
                        'infant' => $infantCount,
                    ],
                    'remarks' => $accommodation->remarks ?? null,
                ];
            })->values()->toArray(),
            'officials' => $package->officials->map(function ($official) use ($officialOpsMap, $accommodationLocations) {
                $officialOps = $officialOpsMap->get((int) $official->id, []);
                $storedHotelsByLocation = collect($officialOps['hotels_by_location'] ?? [])
                    ->filter(fn ($row) => is_array($row))
                    ->map(function ($row): array {
                        return [
                            'location' => $this->normalizeNullableString($row['location'] ?? null),
                            'hotel' => $this->normalizeNullableString($row['hotel'] ?? null),
                        ];
                    })
                    ->filter(fn ($row) => $row['location'] !== null)
                    ->values();
                $fallbackHotel = $this->normalizeNullableString($officialOps['hotel'] ?? null)
                    ?? $this->normalizeNullableString($official->hotel);

                $hotelsByLocation = $accommodationLocations
                    ->map(function ($location) use ($storedHotelsByLocation, $fallbackHotel): array {
                        $matched = $storedHotelsByLocation->first(function (array $row) use ($location): bool {
                            return strtolower((string) $row['location']) === strtolower((string) $location);
                        });

                        return [
                            'location' => $location,
                            'hotel' => $matched['hotel'] ?? $fallbackHotel,
                        ];
                    })
                    ->values();

                if ($hotelsByLocation->isEmpty() && $storedHotelsByLocation->isNotEmpty()) {
                    $hotelsByLocation = $storedHotelsByLocation
                        ->map(function (array $row): array {
                            return [
                                'location' => $row['location'],
                                'hotel' => $row['hotel'],
                            ];
                        })
                        ->values();
                }

                $primaryHotel = $hotelsByLocation
                    ->pluck('hotel')
                    ->map(fn ($hotel) => $this->normalizeNullableString($hotel))
                    ->first(fn ($hotel) => $hotel !== null)
                    ?? $fallbackHotel;

                return [
                    'id' => $official->id,
                    'type' => $official->type,
                    'name' => $official->name,
                    'contact_number' => $official->contact_number,
                    'hotel' => $primaryHotel,
                    'hotels_by_location' => $hotelsByLocation->toArray(),
                ];
            })->values()->toArray(),
            'flights' => $package->flights->map(function ($flight) use ($flightOpsMap) {
                $flightOps = $flightOpsMap->get($flight->id, []);

                return [
                    'id' => $flight->id,
                    'description' => $flight->description,
                    'from' => $flight->from,
                    'departure_datetime' => $flight->departure_datetime_formatted,
                    'airline' => $flight->airline,
                    'pnr' => $flight->pnr,
                    'ic' => $flightOps['ic'] ?? null,
                    'to' => $flight->to,
                    'arrival_datetime' => $flight->arrival_datetime_formatted,
                    'remarks' => $flight->remarks ?? null,
                ];
            })->values()->toArray(),
            'train_tickets' => $package->trainTickets->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'from' => $ticket->from,
                    'to' => $ticket->to,
                    'travel_date' => $ticket->travel_date_formatted,
                    'travel_time' => $ticket->travel_time,
                ];
            })->values()->toArray(),
            'rawdah_tasreehs' => $package->rawdahTasreehs->map(function ($rawdah) {
                return [
                    'id' => $rawdah->id,
                    'date' => $rawdah->date_formatted ?? null,
                    'women_passengers' => $rawdah->women_passengers ?? 0,
                    'women_time' => $rawdah->women_time ?? null,
                    'men_passengers' => $rawdah->men_passengers ?? 0,
                    'men_time' => $rawdah->men_time ?? null,
                    'remarks' => $rawdah->remarks ?? null,
                ];
            })->values()->toArray(),
            'transportation_plans' => $package->transportationPlans->map(function ($transport) {
                return [
                    'id' => $transport->id,
                    'from' => $transport->from ?? null,
                    'to' => $transport->to ?? null,
                    'travel_date' => $transport->travel_date_formatted ?? null,
                    'travel_time' => $transport->travel_time ?? null,
                    'remarks' => $transport->remarks ?? null,
                ];
            })->values()->toArray(),
            'pif' => [
                'tour_leaders' => $tourLeaders,
            ],
        ];
    }

    /**
     * Update editable ops movement fields.
     *
     * @param  array<string, mixed>  $payload
     */
    public function update(int $packageId, array $payload): array
    {
        return DB::transaction(function () use ($packageId, $payload): array {
            $packageQuery = Package::query()->with([
                'accommodations',
                'officials',
                'manifests',
            ]);

            $this->applyOperationsCountryScope($packageQuery);

            $package = $packageQuery->findOrFail($packageId);

            $manifest = $package->manifests->sortByDesc('id')->first();

            if (! $manifest) {
                $manifest = Manifest::create([
                    'package_id' => $package->id,
                    'manifest_number' => NumberGenerator::generate('manifest'),
                ]);
            }

            $package->update([
                'vehicle_type' => $payload['vehicle_type'] ?? $package->vehicle_type,
                'vehicle_driver_name' => $payload['vehicle_driver_name'] ?? $package->vehicle_driver_name,
                'vehicle_driver_contact_number' => $payload['vehicle_driver_contact_number'] ?? $package->vehicle_driver_contact_number,
                'train_description' => $payload['train_description'] ?? $package->train_description,
            ]);

            foreach (($payload['accommodations'] ?? []) as $accommodationPayload) {
                if (empty($accommodationPayload['id'])) {
                    continue;
                }

                $accommodation = $package->accommodations
                    ->firstWhere('id', (int) $accommodationPayload['id']);

                if (! $accommodation) {
                    continue;
                }

                $accommodation->update([
                    'ic' => $accommodationPayload['ic'] ?? null,
                    'remarks' => $accommodationPayload['remarks'] ?? null,
                ]);
            }

            $officialExtensionRows = [];

            foreach (($payload['officials'] ?? []) as $officialPayload) {
                if (empty($officialPayload['id'])) {
                    continue;
                }

                $official = $package->officials
                    ->firstWhere('id', (int) $officialPayload['id']);

                if (! $official) {
                    continue;
                }

                $hotelsByLocation = collect($officialPayload['hotels_by_location'] ?? [])
                    ->filter(fn ($row) => is_array($row))
                    ->map(function ($row): array {
                        return [
                            'location' => $this->normalizeNullableString($row['location'] ?? null),
                            'hotel' => $this->normalizeNullableString($row['hotel'] ?? null),
                        ];
                    })
                    ->filter(fn ($row) => $row['location'] !== null)
                    ->values();

                $primaryHotel = $this->normalizeNullableString($officialPayload['hotel'] ?? null)
                    ?? $hotelsByLocation
                        ->pluck('hotel')
                        ->map(fn ($hotel) => $this->normalizeNullableString($hotel))
                        ->first(fn ($hotel) => $hotel !== null);

                $official->update([
                    'hotel' => $primaryHotel,
                ]);

                $officialExtensionRows[] = [
                    'id' => (int) $official->id,
                    'hotel' => $primaryHotel,
                    'hotels_by_location' => $hotelsByLocation
                        ->map(function (array $row): array {
                            return [
                                'location' => $row['location'],
                                'hotel' => $row['hotel'],
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            }

            $extension = $manifest->ops_movement_extension ?? [];
            $extension['ops_base'] = $payload['ops_base'] ?? null;
            $extension['infotech_ref'] = $payload['infotech_ref'] ?? null;
            $extension['location'] = $payload['location'] ?? null;
            $extension['doa_by'] = $payload['doa_by'] ?? null;
            $extension['doa_datetime'] = $payload['doa_datetime'] ?? null;
            $extension['visa_submitted_to_z_umrah'] = (bool) ($payload['visa_submitted_to_z_umrah'] ?? false);
            $extension['visa_approved'] = (bool) ($payload['visa_approved'] ?? false);

            if (array_key_exists('budget_currency', $payload)) {
                $extension['budget_currency'] = $this->normalizeNullableString($payload['budget_currency'] ?? null) ?? 'SAR';
            }

            if (array_key_exists('officials', $payload) && is_array($payload['officials'])) {
                $extension['officials'] = $officialExtensionRows;
            }
            $extension['flights'] = collect($payload['flights'] ?? [])
                ->filter(fn ($flightPayload) => ! empty($flightPayload['id']))
                ->map(function ($flightPayload) use ($package) {
                    $flight = $package->flights->firstWhere('id', (int) $flightPayload['id']);
                    if ($flight) {
                        $flight->update([
                            'remarks' => $flightPayload['remarks'] ?? null,
                        ]);
                    }

                    return [
                        'id' => (int) $flightPayload['id'],
                        'ic' => $flightPayload['ic'] ?? null,
                    ];
                })
                ->values()
                ->toArray();

            foreach (($payload['rawdah_tasreehs'] ?? []) as $rawdahPayload) {
                if (empty($rawdahPayload['id'])) {
                    continue;
                }

                $rawdah = $package->rawdahTasreehs
                    ->firstWhere('id', (int) $rawdahPayload['id']);

                if (! $rawdah) {
                    continue;
                }

                $rawdah->update([
                    'remarks' => $rawdahPayload['remarks'] ?? null,
                ]);
            }

            foreach (($payload['transportation_plans'] ?? []) as $transportPayload) {
                if (empty($transportPayload['id'])) {
                    continue;
                }

                $transportationPlan = $package->transportationPlans
                    ->firstWhere('id', (int) $transportPayload['id']);

                if (! $transportationPlan) {
                    continue;
                }

                $transportationPlan->update([
                    'remarks' => $transportPayload['remarks'] ?? null,
                ]);
            }
            if (array_key_exists('budget', $payload)) {
                $extension['budget'] = $this->normalizeBudgetPayload($payload['budget'] ?? []);
            }

            $extension['pif'] = [
                'tour_leaders' => $this->normalizePifTourLeaders(
                    data_get($payload, 'pif.tour_leaders', []),
                ),
            ];

            if (array_key_exists('documents', $payload) && is_array($payload['documents'])) {
                $this->syncOpsMovementDocuments($manifest, $payload['documents']);
            }

            $manifest->update([
                'ops_movement_extension' => $extension,
            ]);

            return $this->getForShow($packageId);
        });
    }

    private function applyOperationsCountryScope(Builder $query): void
    {
        $user = DataScope::user();

        if (! $user || ! DataScope::shouldScopeOpsMovementCountry($user)) {
            return;
        }

        $countryIds = DataScope::scopedCountryIds($user);

        if (empty($countryIds)) {
            return;
        }

        $query->whereIn('country_id', $countryIds);
    }

    private function resolveAge(mixed $dateOfBirth, ?Carbon $referenceDate = null): ?int
    {
        if (! $dateOfBirth) {
            return null;
        }

        $resolvedReferenceDate = $referenceDate?->copy() ?? now();

        return Carbon::parse($dateOfBirth)->diffInYears($resolvedReferenceDate);
    }

    private function buildDateRangeLabel(?Carbon $departureDate, ?Carbon $returnDate): ?string
    {
        if (! $departureDate || ! $returnDate) {
            return null;
        }

        if ($departureDate->isSameMonth($returnDate) && $departureDate->isSameYear($returnDate)) {
            return $departureDate->translatedFormat('j').' - '.$returnDate->translatedFormat('j F Y');
        }

        return $departureDate->translatedFormat('j F Y').' - '.$returnDate->translatedFormat('j F Y');
    }

    private function buildOpsMovementNumber(Manifest $manifest): string
    {
        $year = (int) ($manifest->package?->departure_date?->format('Y')
            ?? $manifest->created_at?->format('Y')
            ?? now()->format('Y'));

        $sequence = Manifest::query()
            ->whereYear('created_at', $year)
            ->where('id', '<=', $manifest->id)
            ->count();

        return 'KTG'.$sequence.'-'.substr((string) $year, -2);
    }

    /**
     * @param  array<string, mixed>  $documents
     */
    private function syncOpsMovementDocuments(Manifest $manifest, array $documents): void
    {
        $allowedFields = ['itinerary', 'booklet'];
        $existingFiles = $manifest->files()->whereIn('field', $allowedFields)->get()->groupBy('field');
        $rowsToPersist = [];

        foreach ($allowedFields as $field) {
            $entries = $documents[$field] ?? [];

            if (! is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $isRemoved = (bool) ($entry['removed'] ?? false);
                if ($isRemoved) {
                    continue;
                }

                $uploadedPath = $this->storeDocumentFile($entry['file'] ?? null, "ops-{$field}");
                $requestedName = $this->normalizeNullableString($entry['file_name'] ?? null);
                $existingPath = $this->normalizeNullableString($entry['file_path'] ?? null);
                $filePath = $uploadedPath ?? $existingPath;

                if (! $filePath) {
                    continue;
                }

                $rowsToPersist[] = [
                    'field' => $field,
                    'file_name' => $requestedName ?? pathinfo(basename($filePath), PATHINFO_FILENAME),
                    'file_path' => $filePath,
                ];
            }
        }

        $preservedPaths = collect($rowsToPersist)
            ->pluck('file_path')
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->all();

        foreach ($existingFiles->flatten() as $existingFile) {
            if (! in_array($existingFile->file_path, $preservedPaths, true) && $existingFile->file_path) {
                Storage::disk('public')->delete($existingFile->file_path);
            }
        }

        $manifest->files()->whereIn('field', $allowedFields)->delete();

        foreach ($rowsToPersist as $row) {
            $manifest->files()->create($row);
        }
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildOpsMovementDocumentPayload(?Manifest $manifest): array
    {
        $allowedFields = ['itinerary', 'booklet'];

        if (! $manifest) {
            return collect($allowedFields)
                ->mapWithKeys(fn (string $field) => [$field => []])
                ->all();
        }

        $grouped = $manifest->files->whereIn('field', $allowedFields)->groupBy('field');

        return collect($allowedFields)
            ->mapWithKeys(function (string $field) use ($grouped): array {
                return [
                    $field => ($grouped->get($field) ?? collect())
                        ->map(function ($file): array {
                            return [
                                'id' => $file->id,
                                'file' => null,
                                'file_name' => $file->file_name,
                                'file_path' => $file->file_path,
                                'removed' => false,
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeBudgetPayload(mixed $budget): array
    {
        if (! is_array($budget)) {
            return $this->defaultBudgetSections();
        }

        $sections = array_is_list($budget) ? $budget : [];
        $normalized = [];

        foreach ($sections as $sectionIndex => $section) {
            if (! is_array($section)) {
                continue;
            }

            $itemsInput = isset($section['items']) && is_array($section['items'])
                ? (array_is_list($section['items']) ? $section['items'] : [])
                : [];
            $items = [];

            foreach ($itemsInput as $itemIndex => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $items[] = [
                    'item_name' => $this->normalizeNullableString($item['item_name'] ?? null),
                    'unit_price' => is_numeric($item['unit_price'] ?? null) ? (float) $item['unit_price'] : 0.0,
                    'quantity' => is_numeric($item['quantity'] ?? null) ? (float) $item['quantity'] : 0.0,
                    'remarks' => $this->normalizeNullableString($item['remarks'] ?? null),
                    'sort_order' => isset($item['sort_order']) && is_numeric($item['sort_order'])
                        ? (int) $item['sort_order']
                        : ($itemIndex + 1),
                ];
            }

            $normalized[] = [
                'title' => $this->normalizeNullableString($section['title'] ?? null),
                'sort_order' => isset($section['sort_order']) && is_numeric($section['sort_order'])
                    ? (int) $section['sort_order']
                    : ($sectionIndex + 1),
                'items' => $items,
                'extensions' => $this->normalizeBudgetExtensions($section['extensions'] ?? []),
            ];
        }

        return count($normalized) > 0
            ? $normalized
            : $this->defaultBudgetSections();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultBudgetSections(): array
    {
        return [
            [
                'title' => 'Manpower Expense',
                'sort_order' => 1,
                'items' => [
                    [
                        'item_name' => 'Mutawwif',
                        'unit_price' => 0.0,
                        'quantity' => 0.0,
                        'remarks' => null,
                        'sort_order' => 1,
                    ],
                    [
                        'item_name' => 'Mutawwif Speedtrain',
                        'unit_price' => 0.0,
                        'quantity' => 0.0,
                        'remarks' => null,
                        'sort_order' => 2,
                    ],
                    [
                        'item_name' => 'Mutawwif Meal',
                        'unit_price' => 0.0,
                        'quantity' => 0.0,
                        'remarks' => null,
                        'sort_order' => 3,
                    ],
                    [
                        'item_name' => 'Assisting Mutawwif',
                        'unit_price' => 0.0,
                        'quantity' => 0.0,
                        'remarks' => null,
                        'sort_order' => 4,
                    ],
                    [
                        'item_name' => 'Assisting Mutawwifa',
                        'unit_price' => 0.0,
                        'quantity' => 0.0,
                        'remarks' => null,
                        'sort_order' => 5,
                    ],
                    [
                        'item_name' => 'Mutawifa',
                        'unit_price' => 0.0,
                        'quantity' => 0.0,
                        'remarks' => null,
                        'sort_order' => 6,
                    ],
                    [
                        'item_name' => 'Check-in',
                        'unit_price' => 0.0,
                        'quantity' => 0.0,
                        'remarks' => null,
                        'sort_order' => 7,
                    ],
                ],
                'extensions' => [],
            ],
            [
                'title' => 'Petty Cash',
                'sort_order' => 2,
                'items' => [
                    [
                        'item_name' => 'Hotel Porter',
                        'unit_price' => 0.0,
                        'quantity' => 0.0,
                        'remarks' => null,
                        'sort_order' => 1,
                    ],
                    [
                        'item_name' => 'Bus Tipping',
                        'unit_price' => 0.0,
                        'quantity' => 0.0,
                        'remarks' => null,
                        'sort_order' => 2,
                    ],
                    [
                        'item_name' => 'Tipping for Airport Porter',
                        'unit_price' => 0.0,
                        'quantity' => 0.0,
                        'remarks' => null,
                        'sort_order' => 3,
                    ],
                    [
                        'item_name' => 'Lunch (2nd Umrah)',
                        'unit_price' => 0.0,
                        'quantity' => 0.0,
                        'remarks' => null,
                        'sort_order' => 4,
                    ],
                    [
                        'item_name' => 'Lunch Official',
                        'unit_price' => 0.0,
                        'quantity' => 0.0,
                        'remarks' => null,
                        'sort_order' => 5,
                    ],
                    [
                        'item_name' => 'Customized Sejadah',
                        'unit_price' => 0.0,
                        'quantity' => 0.0,
                        'remarks' => null,
                        'sort_order' => 6,
                    ],
                    [
                        'item_name' => 'Customized Onta',
                        'unit_price' => 0.0,
                        'quantity' => 0.0,
                        'remarks' => null,
                        'sort_order' => 7,
                    ],
                    [
                        'item_name' => 'Zamzam Water',
                        'unit_price' => 0.0,
                        'quantity' => 0.0,
                        'remarks' => null,
                        'sort_order' => 8,
                    ],
                ],
                'extensions' => [],
            ],
            [
                'title' => 'Contingency',
                'sort_order' => 3,
                'items' => [
                    [
                        'item_name' => 'Contingency Fund',
                        'unit_price' => 0.0,
                        'quantity' => 0.0,
                        'remarks' => 'FUND IS TO BE USED SOLELY FOR OPS MATTER ONLY',
                        'sort_order' => 1,
                    ],
                ],
                'extensions' => [],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeBudgetExtensions(mixed $extensions): array
    {
        if (! is_array($extensions)) {
            return [];
        }

        $extensionList = array_is_list($extensions) ? $extensions : [];
        $normalized = [];

        foreach ($extensionList as $extensionIndex => $extension) {
            if (! is_array($extension)) {
                continue;
            }

            $mode = $this->normalizeNullableString($extension['calculation_mode'] ?? null);
            $normalized[] = [
                'name' => $this->normalizeNullableString($extension['name'] ?? null),
                'calculation_mode' => in_array($mode, ['fixed', 'percentage'], true) ? $mode : 'fixed',
                'calculation_value' => is_numeric($extension['calculation_value'] ?? null)
                    ? (float) $extension['calculation_value']
                    : 0.0,
                'sort_order' => isset($extension['sort_order']) && is_numeric($extension['sort_order'])
                    ? (int) $extension['sort_order']
                    : ($extensionIndex + 1),
            ];
        }

        return $normalized;
    }

    private function storeDocumentFile(mixed $file, string $field): ?string
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        return $file->store("manifests/{$field}", 'public');
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array<string, array{single:int,double:int,triple:int,quad:int,child_with_bed:int,child_no_bed:int,infant:int}>
     */
    private function buildRoomCountsByLocationFromManifest(?Manifest $manifest): array
    {
        if (! $manifest) {
            return [];
        }

        $countsByLocation = [];
        $countedMemberIdsByLocation = [];

        foreach ($manifest->rooms ?? [] as $room) {
            $locationKey = $this->normalizeLocationKey($room->location);

            if (! isset($countsByLocation[$locationKey])) {
                $countsByLocation[$locationKey] = [
                    'single' => 0,
                    'double' => 0,
                    'triple' => 0,
                    'quad' => 0,
                    'child_with_bed' => 0,
                    'child_no_bed' => 0,
                    'infant' => 0,
                ];
            }

            $normalizedRoomType = $this->normalizeRoomTypeLabel($room->room_type);

            if ($normalizedRoomType !== null) {
                $countsByLocation[$locationKey][$normalizedRoomType]++;
            }

            foreach ($room->roomMembers ?? [] as $roomMember) {
                $member = $roomMember->member;
                if (! $member) {
                    continue;
                }

                $memberId = (int) ($member->id ?? 0);
                if ($memberId <= 0) {
                    continue;
                }

                if (isset($countedMemberIdsByLocation[$locationKey][$memberId])) {
                    continue;
                }

                $countedMemberIdsByLocation[$locationKey][$memberId] = true;

                $normalizedSharingPlan = $this->normalizeSharingPlan($member->sharing_plan ?? null);

                if (in_array($normalizedSharingPlan, ['child_with_bed', 'child_no_bed', 'infant'], true)) {
                    $countsByLocation[$locationKey][$normalizedSharingPlan]++;
                }
            }

        }

        foreach ($countsByLocation as $locationKey => $roomCounts) {
            $countsByLocation[$locationKey]['single'] +=
                (int) ($roomCounts['child_with_bed'] ?? 0)
                + (int) ($roomCounts['child_no_bed'] ?? 0)
                + (int) ($roomCounts['infant'] ?? 0);
        }

        return $countsByLocation;
    }

    private function normalizeRoomTypeLabel(mixed $roomType): ?string
    {
        if (! is_string($roomType)) {
            return null;
        }

        $normalized = Str::lower(trim($roomType));

        return match ($normalized) {
            'single' => 'single',
            'double', 'twin' => 'double',
            'triple' => 'triple',
            'quad' => 'quad',
            default => null,
        };
    }

    private function normalizeLocationKey(mixed $location): string
    {
        return Str::slug((string) ($location ?? '')) ?: 'general';
    }

    private function normalizeSharingPlan(mixed $sharingPlan): string
    {
        $normalized = Str::lower(trim((string) ($sharingPlan ?? '')));

        return match ($normalized) {
            'child_with_bed' => 'child_with_bed',
            'child_no_bed' => 'child_no_bed',
            'infant' => 'infant',
            default => 'other',
        };
    }

    /**
     * @return array<int, array{type:?string,name:?string,contact_number:?string}>
     */
    private function normalizePifTourLeaders(mixed $tourLeaders): array
    {
        $rows = collect(is_array($tourLeaders) ? $tourLeaders : [])
            ->filter(fn ($row) => is_array($row))
            ->map(function (array $row): array {
                return [
                    'type' => $this->normalizeNullableString($row['type'] ?? null),
                    'name' => $this->normalizeNullableString($row['name'] ?? null),
                    'contact_number' => $this->normalizeNullableString($row['contact_number'] ?? null),
                ];
            })
            ->filter(fn (array $row): bool => $row['type'] !== null || $row['name'] !== null || $row['contact_number'] !== null)
            ->values();

        return $rows->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\PackageOfficial>  $officials
     * @return array<int, array{type:?string,name:?string,contact_number:?string}>
     */
    private function resolvePifTourLeaders(mixed $rawTourLeaders, $officials): array
    {
        $normalizedTourLeaders = $this->normalizePifTourLeaders($rawTourLeaders);

        if (! empty($normalizedTourLeaders)) {
            return $normalizedTourLeaders;
        }

        $officialFallback = collect($officials ?? [])
            ->filter(function ($official): bool {
                $type = Str::lower(trim((string) ($official->type ?? '')));

                return in_array($type, ['mutawif', 'mutawifah', 'official'], true);
            })
            ->map(function ($official): array {
                return [
                    'type' => $this->normalizeNullableString($official->type ?? null),
                    'name' => $this->normalizeNullableString($official->name ?? null),
                    'contact_number' => $this->normalizeNullableString($official->contact_number ?? null),
                ];
            })
            ->values()
            ->all();

        if (! empty($officialFallback)) {
            return $officialFallback;
        }

        return [];
    }
}
