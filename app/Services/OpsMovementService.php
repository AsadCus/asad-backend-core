<?php

namespace App\Services;

use App\Models\Package;

class OpsMovementService
{
    /**
     * Get ops movement data aggregated from packages and their manifests.
     * This is a read-only view — no separate table needed.
     */
    public function getForDataTable(array $filters = [])
    {
        $data = Package::with(['accommodations', 'manifests.travelers'])
            ->when($filters['search'] ?? null, function ($q, $value) {
                $q->where(function ($query) use ($value) {
                    $query->where('package_number', 'like', "%{$value}%")
                        ->orWhere('name', 'like', "%{$value}%")
                        ->orWhere('airline', 'like', "%{$value}%");
                });
            })
            ->when($filters['status'] ?? null, function ($q, $value) {
                $q->where('status', $value);
            })
            ->orderBy('departure_date', 'desc')
            ->get()
            ->map(function ($package) {
                $totalTravelers = $package->manifests->sum(function ($m) {
                    return $m->travelers->count();
                });

                return [
                    'id' => $package->id,
                    'package_number' => $package->package_number,
                    'name' => $package->name,
                    'status' => $package->status,
                    'launched' => $package->launched,
                    'airline' => $package->airline,
                    'pnr' => $package->pnr,
                    'departure_date' => $package->departure_date_formatted,
                    'arrival_date' => $package->arrival_date_formatted,
                    'total_seats' => $package->total_seats,
                    'seats_left' => $package->seats_left,
                    'visa_type' => $package->visa_type,
                    'vehicle_type' => $package->vehicle_type,
                    'ticket_type' => $package->ticket_type,
                    'total_travelers' => $totalTravelers,
                    'manifests_count' => $package->manifests->count(),
                    'accommodations' => $package->accommodations->map(function ($a) {
                        return [
                            'location' => $a->location,
                            'hotel_name' => $a->hotel_name,
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
        $package = Package::with(['accommodations', 'manifests.travelers'])->findOrFail($id);

        return [
            'id' => $package->id,
            'package_number' => $package->package_number,
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
            'remarks' => $package->remarks,
            'accommodations' => $package->accommodations->map(function ($a) {
                return [
                    'location' => $a->location,
                    'hotel_name' => $a->hotel_name,
                    'type_of_meal' => $a->type_of_meal,
                    'check_in' => $a->check_in_formatted,
                    'check_out' => $a->check_out_formatted,
                ];
            })->toArray(),
            'manifests' => $package->manifests->map(function ($m) {
                return [
                    'id' => $m->id,
                    'reference_number' => $m->reference_number,
                    'company_name' => $m->company_name,
                    'status' => $m->status,
                    'travelers_count' => $m->travelers->count(),
                    'travelers' => $m->travelers->map(function ($t) {
                        return [
                            'id' => $t->id,
                            'name' => $t->name,
                            'passport_number' => $t->passport_number,
                            'nationality' => $t->nationality,
                            'traveler_type' => $t->traveler_type,
                        ];
                    })->toArray(),
                ];
            })->toArray(),
        ];
    }
}
