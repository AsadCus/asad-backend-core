<?php

namespace App\Services;

use App\Models\GeneralEnquiry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GeneralEnquiryService
{
    public function getForDataTable(array $filters = [])
    {
        $data = GeneralEnquiry::query()
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
                        ->orWhere('mobile', 'like', "%{$value}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($enquiry) {
                return [
                    'id' => $enquiry->id,
                    'full_name' => $enquiry->full_name,
                    'mobile' => $enquiry->mobile,
                    'email' => $enquiry->email,
                    'preferred_destinations' => $enquiry->preferred_destinations,
                    'preferred_travelling_date' => $enquiry->preferred_travelling_date_formatted,
                    'no_of_adults' => $enquiry->no_of_adults,
                    'no_of_children' => $enquiry->no_of_children,
                    'requires_mobility_assistance' => $enquiry->requires_mobility_assistance,
                    'created_at' => $enquiry->created_at?->translatedFormat('d F Y'),
                    'updated_at' => $enquiry->updated_at?->translatedFormat('d F Y'),
                ];
            });

        return $data;
    }

    public function store(array $data = []): GeneralEnquiry
    {
        return DB::transaction(function () use ($data) {
            if (!empty($data['preferred_travelling_date'])) {
                $data['preferred_travelling_date'] = Carbon::parse($data['preferred_travelling_date'])->format('Y-m-d');
            }

            $enquiry = GeneralEnquiry::create([
                'full_name' => $data['full_name'] ?? null,
                'mobile' => $data['mobile'] ?? null,
                'email' => $data['email'] ?? null,
                'preferred_destinations' => $data['preferred_destinations'] ?? null,
                'preferred_travelling_date' => $data['preferred_travelling_date'] ?? null,
                'no_of_adults' => $data['no_of_adults'] ?? 0,
                'no_of_children' => $data['no_of_children'] ?? 0,
                'requires_mobility_assistance' => $data['requires_mobility_assistance'] ?? null,
            ]);

            return $enquiry;
        });
    }

    public function getForEditShow($id): array
    {
        $enquiry = GeneralEnquiry::findOrFail($id);

        return [
            'id' => $enquiry->id,
            'full_name' => $enquiry->full_name,
            'mobile' => $enquiry->mobile,
            'email' => $enquiry->email,
            'preferred_destinations' => $enquiry->preferred_destinations,
            'preferred_travelling_date' => $enquiry->preferred_travelling_date_formatted,
            'no_of_adults' => $enquiry->no_of_adults,
            'no_of_children' => $enquiry->no_of_children,
            'requires_mobility_assistance' => $enquiry->requires_mobility_assistance,
            'created_at' => $enquiry->created_at?->translatedFormat('d F Y'),
            'updated_at' => $enquiry->updated_at?->translatedFormat('d F Y'),
        ];
    }

    public function update(array $data, int $id): GeneralEnquiry
    {
        return DB::transaction(function () use ($data, $id) {
            $enquiry = GeneralEnquiry::findOrFail($id);

            if (!empty($data['preferred_travelling_date'])) {
                $data['preferred_travelling_date'] = Carbon::parse($data['preferred_travelling_date'])->format('Y-m-d');
            }

            $enquiry->update([
                'full_name' => $data['full_name'] ?? $enquiry->full_name,
                'mobile' => $data['mobile'] ?? $enquiry->mobile,
                'email' => $data['email'] ?? $enquiry->email,
                'preferred_destinations' => $data['preferred_destinations'] ?? $enquiry->preferred_destinations,
                'preferred_travelling_date' => $data['preferred_travelling_date'] ?? $enquiry->preferred_travelling_date,
                'no_of_adults' => $data['no_of_adults'] ?? $enquiry->no_of_adults,
                'no_of_children' => $data['no_of_children'] ?? $enquiry->no_of_children,
                'requires_mobility_assistance' => $data['requires_mobility_assistance'] ?? $enquiry->requires_mobility_assistance,
            ]);

            return $enquiry->fresh();
        });
    }

    public function delete($id)
    {
        $enquiry = GeneralEnquiry::find($id);
        if (!$enquiry) {
            return false;
        }

        return $enquiry->delete();
    }
}
