<?php

namespace App\Services;

use App\Helpers\NumberGenerator;
use App\Models\Manifest;
use App\Models\Package;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

                $primaryManifest = $package->manifests->sortBy('id')->first();

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
            'manifests.files',
        ]);

        $this->applyOperationsCountryScope($packageQuery);

        $package = $packageQuery->findOrFail($id);

        $manifest = $package->manifests->sortBy('id')->first();
        $extension = $manifest?->ops_movement_extension ?? [];
        $flightOpsMap = collect($extension['flights'] ?? [])->keyBy('id');
        $documents = $this->buildOpsMovementDocumentPayload($manifest);
        $budget = $this->normalizeBudgetPayload($extension['budget'] ?? []);
        $nonOfficialMembers = collect($manifest?->members ?? [])
            ->filter(fn ($member) => $member->package_official_id === null && $member->status !== 'cancelled')
            ->values();
        $officialMembers = collect($manifest?->members ?? [])
            ->filter(fn ($member) => $member->package_official_id !== null && $member->status !== 'cancelled')
            ->values();

        $adultMembers = $nonOfficialMembers->filter(fn ($member) => $this->resolveAge($member->date_of_birth) >= 18);
        $childMembers = $nonOfficialMembers->filter(fn ($member) => $this->resolveAge($member->date_of_birth) < 18 && $this->resolveAge($member->date_of_birth) >= 2);

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
                'official_total' => $officialMembers->count(),
                'wheelchair_non_official_total' => $nonOfficialMembers->filter(fn ($member) => $member->is_using_wheelchair === true)->count(),
                'grand_total' => $nonOfficialMembers->count() + $officialMembers->count(),
            ],
            'accommodations' => $package->accommodations->map(function ($accommodation) {
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
                        'double' => 0,
                        'triple' => 0,
                        'quad' => 0,
                    ],
                    'remarks' => $accommodation->remarks ?? null,
                ];
            })->values()->toArray(),
            'officials' => $package->officials->map(function ($official) {
                return [
                    'id' => $official->id,
                    'name' => $official->name,
                    'hotel' => $official->hotel,
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
                'tour_leaders' => $extension['pif']['tour_leaders'] ?? [],
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

            $manifest = $package->manifests->sortBy('id')->first();

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
                ]);
            }

            foreach (($payload['officials'] ?? []) as $officialPayload) {
                if (empty($officialPayload['id'])) {
                    continue;
                }

                $official = $package->officials
                    ->firstWhere('id', (int) $officialPayload['id']);

                if (! $official) {
                    continue;
                }

                $official->update([
                    'hotel' => $officialPayload['hotel'] ?? null,
                ]);
            }

            $extension = $manifest->ops_movement_extension ?? [];
            $extension['ops_base'] = $payload['ops_base'] ?? null;
            $extension['infotech_ref'] = $payload['infotech_ref'] ?? null;
            $extension['location'] = $payload['location'] ?? null;
            $extension['doa_by'] = $payload['doa_by'] ?? null;
            $extension['doa_datetime'] = $payload['doa_datetime'] ?? null;
            $extension['visa_submitted_to_z_umrah'] = (bool) ($payload['visa_submitted_to_z_umrah'] ?? false);
            $extension['visa_approved'] = (bool) ($payload['visa_approved'] ?? false);
            $extension['flights'] = collect($payload['flights'] ?? [])
                ->filter(fn ($flightPayload) => ! empty($flightPayload['id']))
                ->map(function ($flightPayload) {
                    return [
                        'id' => (int) $flightPayload['id'],
                        'ic' => $flightPayload['ic'] ?? null,
                    ];
                })
                ->values()
                ->toArray();
            $extension['budget'] = $this->normalizeBudgetPayload($payload['budget'] ?? []);

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
        $user = auth()->user();

        if (! $user || ! $user->hasRole('operations')) {
            return;
        }

        $countryId = (int) ($user->branch?->country_id ?? 0);

        if ($countryId <= 0) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('country_id', $countryId);
    }

    private function resolveAge(mixed $dateOfBirth): int
    {
        if (! $dateOfBirth) {
            return -1;
        }

        return Carbon::parse($dateOfBirth)->age;
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
            return [];
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
}
