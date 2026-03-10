<?php

namespace App\Services;

use App\Helpers\FormatService;
use App\Helpers\NumberGenerator;
use App\Models\Manifest;
use App\Models\Package;
use App\Models\PrivateEnquiry;
use Illuminate\Support\Facades\DB;

class PackageService
{
    protected $formatService;

    protected PackageSeatService $packageSeatService;

    public function __construct(FormatService $formatService, PackageSeatService $packageSeatService)
    {
        $this->formatService = $formatService;
        $this->packageSeatService = $packageSeatService;
    }

    public function get()
    {
        return Package::get();
    }

    public function getForDataTable(array $filters = [])
    {
        $data = Package::query()
            ->withCount('manifests')
            ->when($filters['search'] ?? null, function ($q, $value) {
                $q->where(function ($query) use ($value) {
                    $query->where('name', 'like', "%{$value}%")
                        ->orWhere('package_number', 'like', "%{$value}%");
                });
            })
            ->when($filters['status'] ?? null, function ($q, $value) {
                $q->where('status', $value);
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($q) {
                return [
                    'id' => $q->id,
                    'package_number' => $q->package_number,
                    'name' => $q->name,
                    'status' => $q->status,
                    'launched' => $q->launched,
                    'departure_date' => $q->departure_date_formatted,
                    'return_date' => $q->return_date_formatted,
                    'total_seats' => $q->total_seats,
                    'seats_left' => $q->seats_left,
                ];
            });

        return $data;
    }

    public function getForFilter()
    {
        return Package::with(['accommodations', 'flights'])->get()->map(function ($q) {
            return [
                'value' => $q->id,
                'label' => $q->name,
                'departure_date' => $q->departure_date_formatted,
                'return_date' => $q->return_date_formatted,
                'accommodations' => $q->accommodations->map(function ($accommodation) {
                    return [
                        'id' => $accommodation->id,
                        'location' => $accommodation->location,
                        'hotel_name' => $accommodation->hotel_name,
                        'type_of_meal' => $accommodation->type_of_meal,
                        'check_in' => $accommodation->check_in_formatted,
                        'check_out' => $accommodation->check_out_formatted,
                    ];
                })->values()->toArray(),
            ];
        });
    }

    public function store(array $data): Package
    {
        return DB::transaction(function () use ($data) {
            $packageNumber = NumberGenerator::generate('package');

            $package = Package::create([
                'package_number' => $packageNumber,
                'name' => $data['name'],
                'status' => $data['status'] ?? 'open',
                'price_single' => $data['price_single'] ?? 0,
                'price_double' => $data['price_double'] ?? 0,
                'price_triple' => $data['price_triple'] ?? 0,
                'price_quad' => $data['price_quad'] ?? 0,
                'child_with_bed_price' => $data['child_with_bed_price'] ?? 0,
                'child_no_bed_price' => $data['child_no_bed_price'] ?? 0,
                'infant_price' => $data['infant_price'] ?? 0,
                'departure_date' => $data['departure_date'] ?? null,
                'return_date' => $data['return_date'] ?? null,
                'total_seats' => $data['total_seats'] ?? null,
                'seats_left' => $data['total_seats'] ?? null,
                'visa_type' => $data['visa_type'] ?? null,
                'vehicle_type' => $data['vehicle_type'] ?? null,
                'ticket_type' => $data['ticket_type'] ?? null,
                'included' => $data['included'] ?? null,
                'not_included' => $data['not_included'] ?? null,
                'offer' => $data['offer'] ?? null,
                'remarks' => $data['remarks'] ?? null,
            ]);

            if (! empty($data['accommodations'])) {
                foreach ($data['accommodations'] as $accommodation) {
                    $package->accommodations()->create([
                        'location' => $accommodation['location'],
                        'hotel_name' => $accommodation['hotel_name'],
                        'type_of_meal' => $accommodation['type_of_meal'] ?? null,
                        'check_in' => $accommodation['check_in'] ?? null,
                        'check_out' => $accommodation['check_out'] ?? null,
                    ]);
                }
            }

            $this->syncFlights($package, $data['flights'] ?? []);
            $this->syncOfficials($package, $data['officials'] ?? []);

            // Auto-create manifest for this package
            $manifestNumber = NumberGenerator::generate('manifest');
            $manifest = Manifest::create([
                'package_id' => $package->id,
                'manifest_number' => $manifestNumber,
                'status' => 'draft',
            ]);

            $this->syncManifestOfficialTravelers($manifest, $package);
            $this->packageSeatService->recalculateForPackageId((int) $package->id);

            activity()
                ->performedOn($package)
                ->withProperties(['subject_type' => 'Package', 'subject_id' => $package->id])
                ->log('Package created successfully #'.$package->package_number);

            return $package;
        });
    }

    public function getForEditShow($id): array
    {
        $package = Package::with(['accommodations', 'flights', 'officials'])->findOrFail($id);

        return [
            'id' => $package->id,
            'package_number' => $package->package_number,
            'name' => $package->name,
            'status' => $package->status,
            'launched' => $package->launched,
            'price_single' => $this->formatService->cleanDecimal($package->price_single),
            'price_double' => $this->formatService->cleanDecimal($package->price_double),
            'price_triple' => $this->formatService->cleanDecimal($package->price_triple),
            'price_quad' => $this->formatService->cleanDecimal($package->price_quad),
            'child_with_bed_price' => $this->formatService->cleanDecimal($package->child_with_bed_price),
            'child_no_bed_price' => $this->formatService->cleanDecimal($package->child_no_bed_price),
            'infant_price' => $this->formatService->cleanDecimal($package->infant_price),
            'departure_date' => $package->departure_date_formatted,
            'return_date' => $package->return_date_formatted,
            'total_seats' => $package->total_seats,
            'seats_left' => $package->seats_left,
            'occupied_seats' => $this->packageSeatService->occupiedSeatsCount((int) $package->id),
            'visa_type' => $package->visa_type,
            'vehicle_type' => $package->vehicle_type,
            'ticket_type' => $package->ticket_type,
            'included' => $package->included,
            'not_included' => $package->not_included,
            'offer' => $package->offer,
            'remarks' => $package->remarks,
            'accommodations' => $package->accommodations->map(function ($a) {
                return [
                    'id' => $a->id,
                    'location' => $a->location,
                    'hotel_name' => $a->hotel_name,
                    'type_of_meal' => $a->type_of_meal,
                    'check_in' => $a->check_in_formatted,
                    'check_out' => $a->check_out_formatted,
                ];
            })->toArray(),
            'flights' => $package->flights->map(function ($f) {
                return [
                    'id' => $f->id,
                    'from' => $f->from,
                    'to' => $f->to,
                    'description' => $f->description,
                    'airline' => $f->airline,
                    'pnr' => $f->pnr,
                    'departure_datetime' => $f->departure_datetime_formatted,
                    'arrival_datetime' => $f->arrival_datetime_formatted,
                ];
            })->toArray(),
            'officials' => $package->officials->map(function ($o) {
                return [
                    'id' => $o->id,
                    'type' => $o->type,
                    'name' => $o->name,
                    'contact_number' => $o->contact_number,
                ];
            })->toArray(),
        ];
    }

    public function update(array $data, int $id): Package
    {
        return DB::transaction(function () use ($data, $id) {
            $package = Package::findOrFail($id);

            $package->update([
                'name' => $data['name'] ?? $package->name,
                'status' => $data['status'] ?? $package->status,
                'price_single' => $data['price_single'] ?? $package->price_single,
                'price_double' => $data['price_double'] ?? $package->price_double,
                'price_triple' => $data['price_triple'] ?? $package->price_triple,
                'price_quad' => $data['price_quad'] ?? $package->price_quad,
                'child_with_bed_price' => $data['child_with_bed_price'] ?? $package->child_with_bed_price,
                'child_no_bed_price' => $data['child_no_bed_price'] ?? $package->child_no_bed_price,
                'infant_price' => $data['infant_price'] ?? $package->infant_price,
                'departure_date' => $data['departure_date'] ?? $package->departure_date,
                'return_date' => $data['return_date'] ?? $package->return_date,
                'total_seats' => $data['total_seats'] ?? $package->total_seats,
                'visa_type' => $data['visa_type'] ?? $package->visa_type,
                'vehicle_type' => $data['vehicle_type'] ?? $package->vehicle_type,
                'ticket_type' => $data['ticket_type'] ?? $package->ticket_type,
                'included' => $data['included'] ?? $package->included,
                'not_included' => $data['not_included'] ?? $package->not_included,
                'offer' => $data['offer'] ?? $package->offer,
                'remarks' => $data['remarks'] ?? $package->remarks,
            ]);

            if (isset($data['accommodations'])) {
                $package->accommodations()->delete();
                foreach ($data['accommodations'] as $accommodation) {
                    $package->accommodations()->create([
                        'location' => $accommodation['location'],
                        'hotel_name' => $accommodation['hotel_name'],
                        'type_of_meal' => $accommodation['type_of_meal'] ?? null,
                        'check_in' => $accommodation['check_in'] ?? null,
                        'check_out' => $accommodation['check_out'] ?? null,
                    ]);
                }
            }

            if (isset($data['flights'])) {
                $this->syncFlights($package, $data['flights']);
            }

            if (isset($data['officials'])) {
                $this->syncOfficials($package, $data['officials']);
                $this->syncPackageOfficialsIntoManifests($package);
            }

            $this->packageSeatService->recalculateForPackageId((int) $package->id);

            $package = $package->fresh();

            activity()
                ->performedOn($package)
                ->withProperties(['subject_type' => 'Package', 'subject_id' => $package->id])
                ->log('Package updated successfully #'.$package->package_number);

            return $package;
        });
    }

    public function delete($id)
    {
        $package = Package::find($id);
        if (! $package) {
            return false;
        }

        activity()
            ->performedOn($package)
            ->withProperties(['subject_type' => 'Package', 'subject_id' => $package->id])
            ->log('Package deleted successfully #'.$package->package_number);

        return $package->delete();
    }

    /**
     * Build package payload from private enquiry data.
     *
     * Only non-empty source values are mapped.
     *
     * @return array<string, mixed>
     */
    public function privateEnquiryToPackagePayload(PrivateEnquiry $privateEnquiry): array
    {
        $enquiry = $privateEnquiry->enquiry;
        $totalSeats = ($privateEnquiry->no_of_pax ?? 0) + ($privateEnquiry->no_of_children ?? 0);

        $payload = [
            'name' => 'Private - '.($enquiry?->name ?? 'Unnamed'),
            'status' => 'open',
            'total_seats' => $totalSeats,
            'seats_left' => $totalSeats,
            'accommodations' => [],
            'flights' => [],
            'officials' => [],
        ];

        $this->setIfNotEmpty($payload, 'departure_date', $privateEnquiry->departure_date_formatted);
        $this->setIfNotEmpty($payload, 'return_date', $privateEnquiry->return_date_formatted);
        $this->setIfNotEmpty($payload, 'vehicle_type', $privateEnquiry->land_transfer);
        $this->setIfNotEmpty($payload, 'ticket_type', $privateEnquiry->add_on_speed_train ? 'speed_train' : null);

        // Build default flight from enquiry airline info
        if (! empty($privateEnquiry->airline)) {
            $payload['flights'][] = [
                'from' => null,
                'to' => null,
                'description' => 'Departure',
                'airline' => $privateEnquiry->airline,
                'pnr' => null,
                'departure_datetime' => null,
                'arrival_datetime' => null,
            ];
            $payload['flights'][] = [
                'from' => null,
                'to' => null,
                'description' => 'Return',
                'airline' => $privateEnquiry->airline,
                'pnr' => null,
                'departure_datetime' => null,
                'arrival_datetime' => null,
            ];
        }

        $included = [];
        $notIncluded = [];
        $offer = [];
        $remarks = [];

        if ($privateEnquiry->require_mutawif) {
            $included[] = 'Mutawif service requested';
        } else {
            $notIncluded[] = 'Mutawif service not requested';
        }

        if ($privateEnquiry->require_umrah_course) {
            $included[] = 'Umrah course requested';
        } else {
            $notIncluded[] = 'Umrah course not requested';
        }

        if ($privateEnquiry->require_umrah_official) {
            $included[] = 'Umrah official requested';
        } else {
            $notIncluded[] = 'Umrah official not requested';
        }

        if ($privateEnquiry->require_meet_greet) {
            $included[] = 'Meet & greet requested';
        } else {
            $notIncluded[] = 'Meet & greet not requested';
        }

        if ($privateEnquiry->require_mutawiffah_ustazah_rawdah) {
            $included[] = 'Mutawiffah/Ustazah Rawdah requested';
        } else {
            $notIncluded[] = 'Mutawiffah/Ustazah Rawdah not requested';
        }

        if ($privateEnquiry->madinah_tour_with_mutawif) {
            $included[] = 'Madinah tour with mutawif requested';
        }

        if ($privateEnquiry->makkah_tour_with_mutawif) {
            $included[] = 'Makkah tour with mutawif requested';
        }

        if ($privateEnquiry->add_on_speed_train) {
            $offer[] = 'Add-on speed train requested';
        }

        $this->setIfNotEmpty($payload, 'included', implode("\n", $included));
        $this->setIfNotEmpty($payload, 'not_included', implode("\n", $notIncluded));
        $this->setIfNotEmpty($payload, 'offer', implode("\n", $offer));

        $remarks[] = 'Private enquiry details:';
        if (! empty($privateEnquiry->class)) {
            $remarks[] = 'Class: '.$privateEnquiry->class;
        }
        if (! empty($privateEnquiry->makkah_or_madinah_first)) {
            $remarks[] = 'Makkah/Madinah first: '.$privateEnquiry->makkah_or_madinah_first;
        }
        if (! empty($privateEnquiry->no_of_nights_makkah)) {
            $remarks[] = 'Nights in Makkah: '.$privateEnquiry->no_of_nights_makkah;
        }
        if (! empty($privateEnquiry->no_of_nights_madinah)) {
            $remarks[] = 'Nights in Madinah: '.$privateEnquiry->no_of_nights_madinah;
        }
        if (! empty($privateEnquiry->need_wheelchair)) {
            $remarks[] = 'Wheelchair support: '.$privateEnquiry->need_wheelchair;
        }
        if ($privateEnquiry->has_chronic_disease) {
            $remarks[] = 'Chronic disease: '.($privateEnquiry->chronic_disease_details ?: 'Yes');
        }
        if (! empty($privateEnquiry->other_remarks)) {
            $remarks[] = 'Other remarks: '.$privateEnquiry->other_remarks;
        }

        $this->setIfNotEmpty($payload, 'remarks', implode("\n", $remarks));

        if (! empty($privateEnquiry->hotel_makkah)) {
            $payload['accommodations'][] = [
                'location' => 'Makkah',
                'hotel_name' => $privateEnquiry->hotel_makkah,
                'type_of_meal' => $privateEnquiry->meals_makkah,
                'check_in' => null,
                'check_out' => null,
            ];
        }

        if (! empty($privateEnquiry->hotel_madinah)) {
            $payload['accommodations'][] = [
                'location' => 'Madinah',
                'hotel_name' => $privateEnquiry->hotel_madinah,
                'type_of_meal' => $privateEnquiry->meals_madinah,
                'check_in' => null,
                'check_out' => null,
            ];
        }

        return $payload;
    }

    /**
     * Create a package from a private enquiry's details.
     *
     * Maps private enquiry fields (airline, dates, hotels, meals, etc.)
     * into a new package + accommodations and links it to the parent enquiry.
     */
    public function createFromPrivateEnquiry(PrivateEnquiry $privateEnquiry): Package
    {
        return DB::transaction(function () use ($privateEnquiry) {
            $enquiry = $privateEnquiry->enquiry;
            $packageNumber = NumberGenerator::generate('package');

            $payload = $this->privateEnquiryToPackagePayload($privateEnquiry);
            $flights = $payload['flights'] ?? [];
            $officials = $payload['officials'] ?? [];
            unset($payload['accommodations'], $payload['flights'], $payload['officials']);

            $package = Package::create(array_merge(
                [
                    'package_number' => $packageNumber,
                ],
                $payload,
            ));

            // Makkah accommodation
            if ($privateEnquiry->hotel_makkah) {
                $makkahNights = (int) ($privateEnquiry->no_of_nights_makkah ?? 0);
                $makkahCheckIn = $privateEnquiry->makkah_or_madinah_first === 'makkah'
                    ? $privateEnquiry->departure_date
                    : ($privateEnquiry->departure_date
                        ? $privateEnquiry->departure_date->copy()->addDays((int) ($privateEnquiry->no_of_nights_madinah ?? 0))
                        : null);
                $makkahCheckOut = $makkahCheckIn && $makkahNights
                    ? $makkahCheckIn->copy()->addDays($makkahNights)
                    : null;

                $package->accommodations()->create([
                    'location' => 'Makkah',
                    'hotel_name' => $privateEnquiry->hotel_makkah,
                    'type_of_meal' => $privateEnquiry->meals_makkah,
                    'check_in' => $makkahCheckIn?->format('d F Y'),
                    'check_out' => $makkahCheckOut?->format('d F Y'),
                ]);
            }

            // Madinah accommodation
            if ($privateEnquiry->hotel_madinah) {
                $madinahNights = (int) ($privateEnquiry->no_of_nights_madinah ?? 0);
                $madinahCheckIn = $privateEnquiry->makkah_or_madinah_first === 'madinah'
                    ? $privateEnquiry->departure_date
                    : ($privateEnquiry->departure_date
                        ? $privateEnquiry->departure_date->copy()->addDays((int) ($privateEnquiry->no_of_nights_makkah ?? 0))
                        : null);
                $madinahCheckOut = $madinahCheckIn && $madinahNights
                    ? $madinahCheckIn->copy()->addDays($madinahNights)
                    : null;

                $package->accommodations()->create([
                    'location' => 'Madinah',
                    'hotel_name' => $privateEnquiry->hotel_madinah,
                    'type_of_meal' => $privateEnquiry->meals_madinah,
                    'check_in' => $madinahCheckIn?->format('d F Y'),
                    'check_out' => $madinahCheckOut?->format('d F Y'),
                ]);
            }

            // Sync flights and officials from payload
            if (! empty($flights)) {
                $this->syncFlights($package, $flights);
            }

            if (! empty($officials)) {
                $this->syncOfficials($package, $officials);
            }

            // Auto-create manifest for this package
            $manifestNumber = NumberGenerator::generate('manifest');
            $manifest = Manifest::create([
                'package_id' => $package->id,
                'manifest_number' => $manifestNumber,
                'status' => 'draft',
            ]);

            $this->syncManifestOfficialTravelers($manifest, $package);
            $this->packageSeatService->recalculateForPackageId((int) $package->id);

            activity()
                ->performedOn($package)
                ->withProperties([
                    'subject_type' => 'Package',
                    'subject_id' => $package->id,
                    'source' => 'private_enquiry',
                    'private_enquiry_id' => $privateEnquiry->id,
                ])
                ->log('Package auto-created from private enquiry #'.$privateEnquiry->id);

            // Link the package to the parent enquiry
            if ($enquiry) {
                $enquiry->update(['package_id' => $package->id]);
            }

            return $package;
        });
    }

    /**
     * Sync flight details for a package (delete + recreate).
     *
     * @param  array<int, array<string, mixed>>  $flights
     */
    private function syncFlights(Package $package, array $flights): void
    {
        $package->flights()->delete();

        foreach ($flights as $index => $flight) {
            $package->flights()->create([
                'from' => $flight['from'] ?? null,
                'to' => $flight['to'] ?? null,
                'description' => $flight['description'] ?? null,
                'airline' => $flight['airline'] ?? null,
                'pnr' => $flight['pnr'] ?? null,
                'departure_datetime' => $flight['departure_datetime'] ?? null,
                'arrival_datetime' => $flight['arrival_datetime'] ?? null,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * Sync officials for a package (delete + recreate).
     *
     * @param  array<int, array<string, mixed>>  $officials
     */
    private function syncOfficials(Package $package, array $officials): void
    {
        $package->officials()->delete();

        foreach ($officials as $index => $official) {
            $package->officials()->create([
                'type' => $official['type'] ?? null,
                'name' => $official['name'] ?? null,
                'contact_number' => $official['contact_number'] ?? null,
                'sort_order' => $index,
            ]);
        }
    }

    private function syncPackageOfficialsIntoManifests(Package $package): void
    {
        $package->loadMissing(['officials', 'manifests']);

        foreach ($package->manifests as $manifest) {
            $this->syncManifestOfficialTravelers($manifest, $package);
        }
    }

    private function syncManifestOfficialTravelers(Manifest $manifest, Package $package): void
    {
        $officialTravelerMarker = '[package-official]';

        $manifest->travelers()
            ->whereNull('customer_id')
            ->whereNull('customer_confirmation_member_id')
            ->where('remarks', 'like', $officialTravelerMarker.'%')
            ->delete();

        $nextSn = ((int) $manifest->travelers()->max('sn')) + 1;

        $package->loadMissing('officials');

        foreach ($package->officials as $official) {
            $displayName = trim((string) ($official->name ?? ''));

            if ($displayName === '') {
                $displayName = ucfirst((string) ($official->type ?? 'Official'));
            }

            $manifest->travelers()->create([
                'sn' => $nextSn++,
                'name_as_per_passport' => $displayName,
                'relationship' => 'official',
                'room_type' => 'Single',
                'bed_type' => 'Single',
                'status' => 'assigned',
                'remarks' => $officialTravelerMarker.' '.($official->type ?? 'official'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function setIfNotEmpty(array &$payload, string $key, mixed $value): void
    {
        if ($value !== null && $value !== '') {
            $payload[$key] = $value;
        }
    }
}
