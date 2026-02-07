<?php

namespace App\Services;

use App\Helpers\NumberGenerator;
use App\Models\Package;
use Carbon\Carbon;
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
                'label' => $q->group_number . ' - ' . $q->name,
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
                'departure_date' => !empty($data['departure_date']) ? Carbon::parse($data['departure_date'])->format('Y-m-d') : null,
                'arrival_date' => !empty($data['arrival_date']) ? Carbon::parse($data['arrival_date'])->format('Y-m-d') : null,
                'total_seats' => $data['total_seats'] ?? null,
                'seats_left' => $data['seats_left'] ?? null,
                'visa_type' => $data['visa_type'] ?? null,
                'vehicle_type' => $data['vehicle_type'] ?? null,
                'ticket_type' => $data['ticket_type'] ?? null,
                'included' => $data['included'] ?? null,
                'not_included' => $data['not_included'] ?? null,
                'remarks' => $data['remarks'] ?? null,
            ]);

            if (!empty($data['accommodations'])) {
                foreach ($data['accommodations'] as $accommodation) {
                    $package->accommodations()->create([
                        'location' => $accommodation['location'],
                        'hotel_name' => $accommodation['hotel_name'],
                        'type_of_meal' => $accommodation['type_of_meal'] ?? null,
                        'check_in' => !empty($accommodation['check_in']) ? Carbon::parse($accommodation['check_in'])->format('Y-m-d') : null,
                        'check_out' => !empty($accommodation['check_out']) ? Carbon::parse($accommodation['check_out'])->format('Y-m-d') : null,
                    ]);
                }
            }

            activity()
                ->performedOn($package)
                ->withProperties(['subject_type' => 'Package', 'subject_id' => $package->id])
                ->log('Package created successfully #' . $package->group_number);

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
                'departure_date' => !empty($data['departure_date']) ? Carbon::parse($data['departure_date'])->format('Y-m-d') : $package->departure_date,
                'arrival_date' => !empty($data['arrival_date']) ? Carbon::parse($data['arrival_date'])->format('Y-m-d') : $package->arrival_date,
                'total_seats' => $data['total_seats'] ?? $package->total_seats,
                'seats_left' => $data['seats_left'] ?? $package->seats_left,
                'visa_type' => $data['visa_type'] ?? $package->visa_type,
                'vehicle_type' => $data['vehicle_type'] ?? $package->vehicle_type,
                'ticket_type' => $data['ticket_type'] ?? $package->ticket_type,
                'included' => $data['included'] ?? $package->included,
                'not_included' => $data['not_included'] ?? $package->not_included,
                'remarks' => $data['remarks'] ?? $package->remarks,
            ]);

            if (isset($data['accommodations'])) {
                $package->accommodations()->delete();
                foreach ($data['accommodations'] as $accommodation) {
                    $package->accommodations()->create([
                        'location' => $accommodation['location'],
                        'hotel_name' => $accommodation['hotel_name'],
                        'type_of_meal' => $accommodation['type_of_meal'] ?? null,
                        'check_in' => !empty($accommodation['check_in']) ? Carbon::parse($accommodation['check_in'])->format('Y-m-d') : null,
                        'check_out' => !empty($accommodation['check_out']) ? Carbon::parse($accommodation['check_out'])->format('Y-m-d') : null,
                    ]);
                }
            }

            $package = $package->fresh();

            activity()
                ->performedOn($package)
                ->withProperties(['subject_type' => 'Package', 'subject_id' => $package->id])
                ->log('Package updated successfully #' . $package->group_number);

            return $package;
        });
    }

    public function delete($id)
    {
        $package = Package::find($id);
        if (!$package) {
            return false;
        }

        activity()
            ->performedOn($package)
            ->withProperties(['subject_type' => 'Package', 'subject_id' => $package->id])
            ->log('Package deleted successfully #' . $package->group_number);

        return $package->delete();
    }
}
