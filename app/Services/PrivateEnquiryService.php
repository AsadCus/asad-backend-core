<?php

namespace App\Services;

use App\Models\PrivateEnquiry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PrivateEnquiryService
{
    public function getForDataTable(array $filters = [])
    {
        $data = PrivateEnquiry::query()
            ->when($filters['from_date'] ?? null, function ($q, $value) {
                $q->whereDate('created_at', '>=', $value);
            })
            ->when($filters['to_date'] ?? null, function ($q, $value) {
                $q->whereDate('created_at', '<=', $value);
            })
            ->when($filters['search'] ?? null, function ($q, $value) {
                $q->where(function ($query) use ($value) {
                    $query->where('full_name', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%")
                        ->orWhere('contact_number', 'like', "%{$value}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($enquiry) {
                return [
                    'id' => $enquiry->id,
                    'full_name' => $enquiry->full_name,
                    'contact_number' => $enquiry->contact_number,
                    'email' => $enquiry->email,
                    'passport_expiry_date' => $enquiry->passport_expiry_date,
                    'departure_date' => $enquiry->departure_date,
                    'return_date' => $enquiry->return_date,
                    'no_of_pax' => $enquiry->no_of_pax,
                    'no_of_children' => $enquiry->no_of_children,
                    'airline' => $enquiry->airline,
                    'class' => $enquiry->class,
                    'require_mutawif' => $enquiry->require_mutawif,
                    'require_umrah_course' => $enquiry->require_umrah_course,
                    'require_umrah_official' => $enquiry->require_umrah_official,
                    'makkah_or_madinah_first' => $enquiry->makkah_or_madinah_first,
                    'no_of_nights_makkah' => $enquiry->no_of_nights_makkah,
                    'hotel_makkah' => $enquiry->hotel_makkah,
                    'meals_makkah' => $enquiry->meals_makkah,
                    'no_of_nights_madinah' => $enquiry->no_of_nights_madinah,
                    'hotel_madinah' => $enquiry->hotel_madinah,
                    'meals_madinah' => $enquiry->meals_madinah,
                    'land_transfer' => $enquiry->land_transfer,
                    'add_on_speed_train' => $enquiry->add_on_speed_train,
                    'require_meet_greet' => $enquiry->require_meet_greet,
                    'require_mutawiffah_ustazah_rawdah' => $enquiry->require_mutawiffah_ustazah_rawdah,
                    'madinah_tour_with_mutawif' => $enquiry->madinah_tour_with_mutawif,
                    'makkah_tour_with_mutawif' => $enquiry->makkah_tour_with_mutawif,
                    'has_chronic_disease' => $enquiry->has_chronic_disease,
                    'chronic_disease_details' => $enquiry->chronic_disease_details,
                    'need_wheelchair' => $enquiry->need_wheelchair,
                    'other_remarks' => $enquiry->other_remarks,
                    'created_at' => $enquiry->created_at?->translatedFormat('d F Y'),
                    'updated_at' => $enquiry->updated_at?->translatedFormat('d F Y'),
                ];
            });

        return $data;
    }

    public function store(array $data = []): PrivateEnquiry
    {
        return DB::transaction(function () use ($data) {
            // Format date fields if present
            foreach ([
                'passport_expiry_date',
                'departure_date',
                'return_date',
            ] as $dateField) {
                if (!empty($data[$dateField])) {
                    $data[$dateField] = Carbon::parse($data[$dateField])->format('Y-m-d');
                }
            }

            $enquiry = PrivateEnquiry::create([
                'full_name' => $data['full_name'] ?? null,
                'contact_number' => $data['contact_number'] ?? null,
                'email' => $data['email'] ?? null,
                'passport_expiry_date' => $data['passport_expiry_date'] ?? null,
                'departure_date' => $data['departure_date'] ?? null,
                'return_date' => $data['return_date'] ?? null,
                'no_of_pax' => $data['no_of_pax'] ?? 0,
                'no_of_children' => $data['no_of_children'] ?? 0,
                'airline' => $data['airline'] ?? null,
                'class' => $data['class'] ?? null,
                'require_mutawif' => $data['require_mutawif'] ?? false,
                'require_umrah_course' => $data['require_umrah_course'] ?? false,
                'require_umrah_official' => $data['require_umrah_official'] ?? false,
                'makkah_or_madinah_first' => $data['makkah_or_madinah_first'] ?? null,
                'no_of_nights_makkah' => $data['no_of_nights_makkah'] ?? null,
                'hotel_makkah' => $data['hotel_makkah'] ?? null,
                'meals_makkah' => $data['meals_makkah'] ?? null,
                'no_of_nights_madinah' => $data['no_of_nights_madinah'] ?? null,
                'hotel_madinah' => $data['hotel_madinah'] ?? null,
                'meals_madinah' => $data['meals_madinah'] ?? null,
                'land_transfer' => $data['land_transfer'] ?? null,
                'add_on_speed_train' => $data['add_on_speed_train'] ?? false,
                'require_meet_greet' => $data['require_meet_greet'] ?? false,
                'require_mutawiffah_ustazah_rawdah' => $data['require_mutawiffah_ustazah_rawdah'] ?? false,
                'madinah_tour_with_mutawif' => $data['madinah_tour_with_mutawif'] ?? false,
                'makkah_tour_with_mutawif' => $data['makkah_tour_with_mutawif'] ?? false,
                'has_chronic_disease' => $data['has_chronic_disease'] ?? false,
                'chronic_disease_details' => $data['chronic_disease_details'] ?? null,
                'need_wheelchair' => $data['need_wheelchair'] ?? null,
                'other_remarks' => $data['other_remarks'] ?? null,
            ]);

            activity()
                ->performedOn($enquiry)
                ->withProperties(['subject_type' => 'PrivateEnquiry', 'subject_id' => $enquiry->id, 'enquiry_id' => $enquiry->id])
                ->log('Private enquiry created successfully #' . $enquiry->id);

            return $enquiry;
        });
    }

    public function getForEditShow($id): array
    {
        $enquiry = PrivateEnquiry::findOrFail($id);

        return [
            'id' => $enquiry->id,
            'full_name' => $enquiry->full_name,
            'contact_number' => $enquiry->contact_number,
            'email' => $enquiry->email,
            'passport_expiry_date' => $enquiry->passport_expiry_date,
            'departure_date' => $enquiry->departure_date,
            'return_date' => $enquiry->return_date,
            'no_of_pax' => $enquiry->no_of_pax,
            'no_of_children' => $enquiry->no_of_children,
            'airline' => $enquiry->airline,
            'class' => $enquiry->class,
            'require_mutawif' => $enquiry->require_mutawif,
            'require_umrah_course' => $enquiry->require_umrah_course,
            'require_umrah_official' => $enquiry->require_umrah_official,
            'makkah_or_madinah_first' => $enquiry->makkah_or_madinah_first,
            'no_of_nights_makkah' => $enquiry->no_of_nights_makkah,
            'hotel_makkah' => $enquiry->hotel_makkah,
            'meals_makkah' => $enquiry->meals_makkah,
            'no_of_nights_madinah' => $enquiry->no_of_nights_madinah,
            'hotel_madinah' => $enquiry->hotel_madinah,
            'meals_madinah' => $enquiry->meals_madinah,
            'land_transfer' => $enquiry->land_transfer,
            'add_on_speed_train' => $enquiry->add_on_speed_train,
            'require_meet_greet' => $enquiry->require_meet_greet,
            'require_mutawiffah_ustazah_rawdah' => $enquiry->require_mutawiffah_ustazah_rawdah,
            'madinah_tour_with_mutawif' => $enquiry->madinah_tour_with_mutawif,
            'makkah_tour_with_mutawif' => $enquiry->makkah_tour_with_mutawif,
            'has_chronic_disease' => $enquiry->has_chronic_disease,
            'chronic_disease_details' => $enquiry->chronic_disease_details,
            'need_wheelchair' => $enquiry->need_wheelchair,
            'other_remarks' => $enquiry->other_remarks,
            'created_at' => $enquiry->created_at?->translatedFormat('d F Y'),
            'updated_at' => $enquiry->updated_at?->translatedFormat('d F Y'),
        ];
    }

    public function update(array $data, int $id): PrivateEnquiry
    {
        return DB::transaction(function () use ($data, $id) {
            $enquiry = PrivateEnquiry::findOrFail($id);

            foreach ([
                'passport_expiry_date',
                'departure_date',
                'return_date',
            ] as $dateField) {
                if (!empty($data[$dateField])) {
                    $data[$dateField] = Carbon::parse($data[$dateField])->format('Y-m-d');
                }
            }

            $enquiry->update([
                'full_name' => $data['full_name'] ?? $enquiry->full_name,
                'contact_number' => $data['contact_number'] ?? $enquiry->contact_number,
                'email' => $data['email'] ?? $enquiry->email,
                'passport_expiry_date' => $data['passport_expiry_date'] ?? $enquiry->passport_expiry_date,
                'departure_date' => $data['departure_date'] ?? $enquiry->departure_date,
                'return_date' => $data['return_date'] ?? $enquiry->return_date,
                'no_of_pax' => $data['no_of_pax'] ?? $enquiry->no_of_pax,
                'no_of_children' => $data['no_of_children'] ?? $enquiry->no_of_children,
                'airline' => $data['airline'] ?? $enquiry->airline,
                'class' => $data['class'] ?? $enquiry->class,
                'require_mutawif' => $data['require_mutawif'] ?? $enquiry->require_mutawif,
                'require_umrah_course' => $data['require_umrah_course'] ?? $enquiry->require_umrah_course,
                'require_umrah_official' => $data['require_umrah_official'] ?? $enquiry->require_umrah_official,
                'makkah_or_madinah_first' => $data['makkah_or_madinah_first'] ?? $enquiry->makkah_or_madinah_first,
                'no_of_nights_makkah' => $data['no_of_nights_makkah'] ?? $enquiry->no_of_nights_makkah,
                'hotel_makkah' => $data['hotel_makkah'] ?? $enquiry->hotel_makkah,
                'meals_makkah' => $data['meals_makkah'] ?? $enquiry->meals_makkah,
                'no_of_nights_madinah' => $data['no_of_nights_madinah'] ?? $enquiry->no_of_nights_madinah,
                'hotel_madinah' => $data['hotel_madinah'] ?? $enquiry->hotel_madinah,
                'meals_madinah' => $data['meals_madinah'] ?? $enquiry->meals_madinah,
                'land_transfer' => $data['land_transfer'] ?? $enquiry->land_transfer,
                'add_on_speed_train' => $data['add_on_speed_train'] ?? $enquiry->add_on_speed_train,
                'require_meet_greet' => $data['require_meet_greet'] ?? $enquiry->require_meet_greet,
                'require_mutawiffah_ustazah_rawdah' => $data['require_mutawiffah_ustazah_rawdah'] ?? $enquiry->require_mutawiffah_ustazah_rawdah,
                'madinah_tour_with_mutawif' => $data['madinah_tour_with_mutawif'] ?? $enquiry->madinah_tour_with_mutawif,
                'makkah_tour_with_mutawif' => $data['makkah_tour_with_mutawif'] ?? $enquiry->makkah_tour_with_mutawif,
                'has_chronic_disease' => $data['has_chronic_disease'] ?? $enquiry->has_chronic_disease,
                'chronic_disease_details' => $data['chronic_disease_details'] ?? $enquiry->chronic_disease_details,
                'need_wheelchair' => $data['need_wheelchair'] ?? $enquiry->need_wheelchair,
                'other_remarks' => $data['other_remarks'] ?? $enquiry->other_remarks,
            ]);

            $enquiry = $enquiry->fresh();

            activity()
                ->performedOn($enquiry)
                ->withProperties(['subject_type' => 'PrivateEnquiry', 'subject_id' => $enquiry->id, 'enquiry_id' => $enquiry->id])
                ->log('Private enquiry updated successfully #' . $enquiry->id);

            return $enquiry;
        });
    }

    public function delete($id)
    {
        $enquiry = PrivateEnquiry::find($id);
        if (!$enquiry) {
            return false;
        }

        activity()
            ->performedOn($enquiry)
            ->withProperties(['subject_type' => 'PrivateEnquiry', 'subject_id' => $enquiry->id, 'enquiry_id' => $enquiry->id])
            ->log('Private enquiry deleted successfully #' . $enquiry->id);

        return $enquiry->delete();
    }
}