<?php

namespace App\Services;

use App\Helpers\NumberGenerator;
use App\Models\Package;
use App\Models\PrivateEnquiry;
use Illuminate\Support\Facades\DB;

class PackageService
{
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
                        ->orWhere('group_number', 'like', "%{$value}%")
                        ->orWhere('airline', 'like', "%{$value}%");
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
                    'group_number' => $q->group_number,
                    'name' => $q->name,
                    'status' => $q->status,
                    'launched' => $q->launched,
                    'airline' => $q->airline,
                    'departure_date' => $q->departure_date_formatted,
                    'arrival_date' => $q->arrival_date_formatted,
                    'total_seats' => $q->total_seats,
                    'seats_left' => $q->seats_left,
                    'price_quad' => $q->price_quad,
                    'manifests_count' => $q->manifests_count,
                    'created_at' => $q->created_at?->translatedFormat('d F Y'),
                ];
            });

        return $data;
    }

    public function getForFilter()
    {
        return Package::get()->map(function ($q) {
            return [
                'value' => $q->id,
                // 'label' => $q->group_number . ' - ' . $q->name,
                'label' => $q->name,
            ];
        });
    }

    public function getForFilterByName()
    {
        return Package::get()->map(function ($q) {
            return [
                'value' => $q->name,
                'label' => $q->name,
            ];
        });
    }

    public function store(array $data): Package
    {
        return DB::transaction(function () use ($data) {
            $groupNumber = NumberGenerator::generate('package');

            $package = Package::create([
                'group_number' => $groupNumber,
                'name' => $data['name'],
                'status' => $data['status'] ?? 'open',
                'price_single' => $data['price_single'] ?? 0,
                'price_double' => $data['price_double'] ?? 0,
                'price_triple' => $data['price_triple'] ?? 0,
                'price_quad' => $data['price_quad'] ?? 0,
                'child_with_bed_price' => $data['child_with_bed_price'] ?? 0,
                'child_no_bed_price' => $data['child_no_bed_price'] ?? 0,
                'infant_price' => $data['infant_price'] ?? 0,
                'airline' => $data['airline'] ?? null,
                'pnr' => $data['pnr'] ?? null,
                'departure_date' => $data['departure_date'] ?? null,
                'arrival_date' => $data['arrival_date'] ?? null,
                'total_seats' => $data['total_seats'] ?? null,
                'seats_left' => $data['seats_left'] ?? null,
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

            activity()
                ->performedOn($package)
                ->withProperties(['subject_type' => 'Package', 'subject_id' => $package->id])
                ->log('Package created successfully #'.$package->group_number);

            return $package;
        });
    }

    public function getForEditShow($id): array
    {
        $package = Package::with('accommodations')->findOrFail($id);

        return [
            'id' => $package->id,
            'group_number' => $package->group_number,
            'name' => $package->name,
            'status' => $package->status,
            'launched' => $package->launched,
            'price_single' => $package->price_single,
            'price_double' => $package->price_double,
            'price_triple' => $package->price_triple,
            'price_quad' => $package->price_quad,
            'child_with_bed_price' => $package->child_with_bed_price,
            'child_no_bed_price' => $package->child_no_bed_price,
            'infant_price' => $package->infant_price,
            'airline' => $package->airline,
            'pnr' => $package->pnr,
            'departure_date' => $package->departure_date_formatted,
            'arrival_date' => $package->arrival_date_formatted,
            'total_seats' => $package->total_seats,
            'seats_left' => $package->seats_left,
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
                'airline' => $data['airline'] ?? $package->airline,
                'pnr' => $data['pnr'] ?? $package->pnr,
                'departure_date' => $data['departure_date'] ?? $package->departure_date,
                'arrival_date' => $data['arrival_date'] ?? $package->arrival_date,
                'total_seats' => $data['total_seats'] ?? $package->total_seats,
                'seats_left' => $data['seats_left'] ?? $package->seats_left,
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

            $package = $package->fresh();

            activity()
                ->performedOn($package)
                ->withProperties(['subject_type' => 'Package', 'subject_id' => $package->id])
                ->log('Package updated successfully #'.$package->group_number);

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
            ->log('Package deleted successfully #'.$package->group_number);

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
        ];

        $this->setIfNotEmpty($payload, 'airline', $privateEnquiry->airline);
        $this->setIfNotEmpty($payload, 'departure_date', $privateEnquiry->departure_date?->format('d F Y'));
        $this->setIfNotEmpty($payload, 'arrival_date', $privateEnquiry->return_date?->format('d F Y'));
        $this->setIfNotEmpty($payload, 'vehicle_type', $privateEnquiry->land_transfer);
        $this->setIfNotEmpty($payload, 'ticket_type', $privateEnquiry->add_on_speed_train ? 'speed_train' : null);
        $this->setIfNotEmpty($payload, 'remarks', $privateEnquiry->other_remarks);

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
            $groupNumber = NumberGenerator::generate('package');

            $payload = $this->privateEnquiryToPackagePayload($privateEnquiry);
            unset($payload['accommodations']);

            $package = Package::create(array_merge(
                [
                    'group_number' => $groupNumber,
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
     * @param  array<string, mixed>  $payload
     */
    private function setIfNotEmpty(array &$payload, string $key, mixed $value): void
    {
        if ($value !== null && $value !== '') {
            $payload[$key] = $value;
        }
    }
}
