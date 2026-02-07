<?php

namespace App\Services;

use App\Models\Manifest;
use App\Models\ManifestTraveler;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ManifestService
{
    public function get()
    {
        $data = Manifest::get();

        return $data;
    }

    public function getForDataTable(array $filters = [])
    {
        $data = Manifest::with('package')
            ->when($filters['search'] ?? null, function ($q, $value) {
                $q->where(function ($query) use ($value) {
                    $query->where('reference_number', 'like', "%{$value}%")
                        ->orWhereHas('package', function ($pq) use ($value) {
                            $pq->where('name', 'like', "%{$value}%");
                        });
                });
            })
            ->when($filters['status'] ?? null, function ($q, $value) {
                $q->where('status', $value);
            })
            ->orderBy('departure_date', 'desc')
            ->get()
            ->map(function ($q) {
                return [
                    'id' => $q->id,
                    'package_id' => $q->package_id,
                    'package_name' => $q->package?->name,
                    'reference_number' => $q->reference_number,
                    'departure_date' => $q->departure_date_formatted,
                    'return_date' => $q->return_date_formatted,
                    'duration' => $q->duration,
                    'makkah_hotel' => $q->makkah_hotel,
                    'madinah_hotel' => $q->madinah_hotel,
                    'status' => $q->status,
                    'travelers_count' => $q->travelers()->count(),
                    'created_at' => $q->created_at?->translatedFormat('d F Y'),
                ];
            });

        return $data;
    }

    public function getForFilter()
    {
        $data = Manifest::get()->map(function ($q) {
            return [
                'value' => $q->id,
                'label' => $q->reference_number,
            ];
        });

        return $data;
    }

    public function getForFilterByName()
    {
        $data = Manifest::get()->map(function ($q) {
            return [
                'value' => $q->reference_number,
                'label' => $q->reference_number,
            ];
        });

        return $data;
    }

    public function store(array $data): Manifest
    {
        return DB::transaction(function () use ($data) {
            $dateFields = ['departure_date', 'return_date', 'makkah_check_in', 'makkah_check_out', 'madinah_check_in', 'madinah_check_out'];
            foreach ($dateFields as $field) {
                if (!empty($data[$field])) {
                    $data[$field] = Carbon::parse($data[$field])->format('Y-m-d');
                }
            }

            $manifest = Manifest::create([
                'package_id' => $data['package_id'],
                'reference_number' => $data['reference_number'],
                'company_address' => $data['company_address'] ?? null,
                'company_phone' => $data['company_phone'] ?? null,
                'departure_date' => $data['departure_date'],
                'return_date' => $data['return_date'],
                'duration' => $data['duration'] ?? null,
                'makkah_hotel' => $data['makkah_hotel'] ?? null,
                'makkah_check_in' => $data['makkah_check_in'] ?? null,
                'makkah_check_out' => $data['makkah_check_out'] ?? null,
                'madinah_hotel' => $data['madinah_hotel'] ?? null,
                'madinah_check_in' => $data['madinah_check_in'] ?? null,
                'madinah_check_out' => $data['madinah_check_out'] ?? null,
                'flight_details' => $data['flight_details'] ?? null,
                'notes' => $data['notes'] ?? null,
                'first_meal' => $data['first_meal'] ?? null,
                'last_meal' => $data['last_meal'] ?? null,
                'status' => $data['status'] ?? 'draft',
            ]);

            if (!empty($data['travelers'])) {
                foreach ($data['travelers'] as $index => $traveler) {
                    $manifest->travelers()->create([
                        'sn' => $index + 1,
                        'name_as_per_passport' => $traveler['name_as_per_passport'],
                        'relationship' => $traveler['relationship'] ?? null,
                        'passport_no' => $traveler['passport_no'] ?? null,
                        'room_no' => $traveler['room_no'] ?? null,
                        'room_type' => $traveler['room_type'] ?? null,
                        'bed_type' => $traveler['bed_type'] ?? null,
                        'date_of_birth' => !empty($traveler['date_of_birth']) ? Carbon::parse($traveler['date_of_birth'])->format('Y-m-d') : null,
                        'age' => $traveler['age'] ?? null,
                        'no_of_beds_checked' => $traveler['no_of_beds_checked'] ?? null,
                        'meal' => $traveler['meal'] ?? null,
                        'remarks' => $traveler['remarks'] ?? null,
                        'total_cost' => $traveler['total_cost'] ?? 0,
                        'total_paid' => $traveler['total_paid'] ?? 0,
                        'outstanding_amount' => $traveler['outstanding_amount'] ?? 0,
                    ]);
                }
            }

            activity()
                ->performedOn($manifest)
                ->withProperties(['subject_type' => 'Manifest', 'subject_id' => $manifest->id])
                ->log('Manifest created successfully #' . $manifest->id);

            return $manifest;
        });
    }

    public function getForEditShow($id): array
    {
        $manifest = Manifest::with(['package', 'travelers', 'rooms', 'payments'])->findOrFail($id);

        return [
            'id' => $manifest->id,
            'package_id' => $manifest->package_id,
            'package_name' => $manifest->package?->name,
            'reference_number' => $manifest->reference_number,
            'company_address' => $manifest->company_address,
            'company_phone' => $manifest->company_phone,
            'departure_date' => $manifest->departure_date_formatted,
            'return_date' => $manifest->return_date_formatted,
            'duration' => $manifest->duration,
            'makkah_hotel' => $manifest->makkah_hotel,
            'makkah_check_in' => $manifest->makkah_check_in?->format('d/m/Y'),
            'makkah_check_out' => $manifest->makkah_check_out?->format('d/m/Y'),
            'madinah_hotel' => $manifest->madinah_hotel,
            'madinah_check_in' => $manifest->madinah_check_in?->format('d/m/Y'),
            'madinah_check_out' => $manifest->madinah_check_out?->format('d/m/Y'),
            'flight_details' => $manifest->flight_details ?? [],
            'notes' => $manifest->notes,
            'first_meal' => $manifest->first_meal,
            'last_meal' => $manifest->last_meal,
            'status' => $manifest->status,
            'travelers' => $manifest->travelers->map(function ($t) {
                return [
                    'id' => $t->id,
                    'sn' => $t->sn,
                    'name_as_per_passport' => $t->name_as_per_passport,
                    'relationship' => $t->relationship,
                    'passport_no' => $t->passport_no,
                    'room_no' => $t->room_no,
                    'room_type' => $t->room_type,
                    'bed_type' => $t->bed_type,
                    'date_of_birth' => $t->date_of_birth_formatted,
                    'age' => $t->age,
                    'no_of_beds_checked' => $t->no_of_beds_checked,
                    'meal' => $t->meal,
                    'remarks' => $t->remarks,
                    'total_cost' => $t->total_cost,
                    'total_paid' => $t->total_paid,
                    'outstanding_amount' => $t->outstanding_amount,
                ];
            })->toArray(),
            'rooms' => $manifest->rooms->map(function ($r) {
                return [
                    'id' => $r->id,
                    'location' => $r->location,
                    'room_number' => $r->room_number,
                    'room_type' => $r->room_type,
                    'bed_type' => $r->bed_type,
                    'capacity' => $r->capacity,
                ];
            })->toArray(),
            'payments' => $manifest->payments->map(function ($p) {
                return [
                    'id' => $p->id,
                    'traveler_name' => $p->traveler_name,
                    'description' => $p->description,
                    'amount' => $p->amount,
                    'paid_amount' => $p->paid_amount,
                    'outstanding_amount' => $p->outstanding_amount,
                    'payment_date' => $p->payment_date_formatted,
                    'status' => $p->status,
                ];
            })->toArray(),
        ];
    }

    public function update(array $data, int $id): Manifest
    {
        return DB::transaction(function () use ($data, $id) {
            $manifest = Manifest::findOrFail($id);

            $dateFields = ['departure_date', 'return_date', 'makkah_check_in', 'makkah_check_out', 'madinah_check_in', 'madinah_check_out'];
            foreach ($dateFields as $field) {
                if (!empty($data[$field])) {
                    $data[$field] = Carbon::parse($data[$field])->format('Y-m-d');
                }
            }

            $manifest->update([
                'package_id' => $data['package_id'] ?? $manifest->package_id,
                'reference_number' => $data['reference_number'] ?? $manifest->reference_number,
                'company_address' => $data['company_address'] ?? $manifest->company_address,
                'company_phone' => $data['company_phone'] ?? $manifest->company_phone,
                'departure_date' => $data['departure_date'] ?? $manifest->departure_date,
                'return_date' => $data['return_date'] ?? $manifest->return_date,
                'duration' => $data['duration'] ?? $manifest->duration,
                'makkah_hotel' => $data['makkah_hotel'] ?? $manifest->makkah_hotel,
                'makkah_check_in' => $data['makkah_check_in'] ?? $manifest->makkah_check_in,
                'makkah_check_out' => $data['makkah_check_out'] ?? $manifest->makkah_check_out,
                'madinah_hotel' => $data['madinah_hotel'] ?? $manifest->madinah_hotel,
                'madinah_check_in' => $data['madinah_check_in'] ?? $manifest->madinah_check_in,
                'madinah_check_out' => $data['madinah_check_out'] ?? $manifest->madinah_check_out,
                'flight_details' => $data['flight_details'] ?? $manifest->flight_details,
                'notes' => $data['notes'] ?? $manifest->notes,
                'first_meal' => $data['first_meal'] ?? $manifest->first_meal,
                'last_meal' => $data['last_meal'] ?? $manifest->last_meal,
                'status' => $data['status'] ?? $manifest->status,
            ]);

            if (isset($data['travelers'])) {
                $manifest->travelers()->delete();
                foreach ($data['travelers'] as $index => $traveler) {
                    $manifest->travelers()->create([
                        'sn' => $index + 1,
                        'name_as_per_passport' => $traveler['name_as_per_passport'],
                        'relationship' => $traveler['relationship'] ?? null,
                        'passport_no' => $traveler['passport_no'] ?? null,
                        'room_no' => $traveler['room_no'] ?? null,
                        'room_type' => $traveler['room_type'] ?? null,
                        'bed_type' => $traveler['bed_type'] ?? null,
                        'date_of_birth' => !empty($traveler['date_of_birth']) ? Carbon::parse($traveler['date_of_birth'])->format('Y-m-d') : null,
                        'age' => $traveler['age'] ?? null,
                        'no_of_beds_checked' => $traveler['no_of_beds_checked'] ?? null,
                        'meal' => $traveler['meal'] ?? null,
                        'remarks' => $traveler['remarks'] ?? null,
                        'total_cost' => $traveler['total_cost'] ?? 0,
                        'total_paid' => $traveler['total_paid'] ?? 0,
                        'outstanding_amount' => $traveler['outstanding_amount'] ?? 0,
                    ]);
                }
            }

            $manifest = $manifest->fresh();

            activity()
                ->performedOn($manifest)
                ->withProperties(['subject_type' => 'Manifest', 'subject_id' => $manifest->id])
                ->log('Manifest updated successfully #' . $manifest->id);

            return $manifest;
        });
    }

    public function delete($id)
    {
        $manifest = Manifest::find($id);
        if (!$manifest) {
            return false;
        }

        activity()
            ->performedOn($manifest)
            ->withProperties(['subject_type' => 'Manifest', 'subject_id' => $manifest->id])
            ->log('Manifest deleted successfully #' . $manifest->id);

        return $manifest->delete();
    }
}
