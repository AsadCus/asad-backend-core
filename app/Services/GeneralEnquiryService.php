<?php

namespace App\Services;

use App\Enums\EnquiryStatus;
use App\Models\Enquiry;
use App\Models\GeneralEnquiry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GeneralEnquiryService
{
    public function getForDataTable(array $filters = [])
    {
        $data = GeneralEnquiry::query()
            ->with('enquiry')
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
                    'enquiry_id' => $enquiry->enquiry_id,
                    'status' => $enquiry->enquiry?->status?->value ?? 'new_lead',
                    'status_label' => $enquiry->enquiry?->status?->label() ?? 'New Lead',
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
            if (! empty($data['preferred_travelling_date'])) {
                $data['preferred_travelling_date'] = Carbon::parse($data['preferred_travelling_date'])->format('Y-m-d');
            }

            // Create parent enquiry record
            $parentEnquiry = Enquiry::create([
                'type' => 'general',
                'status' => EnquiryStatus::NewLead->value,
                'full_name' => $data['full_name'] ?? '',
                'contact_number' => $data['mobile'] ?? '',
                'email' => $data['email'] ?? '',
                'created_by' => auth()->id(),
            ]);

            $enquiry = GeneralEnquiry::create([
                'enquiry_id' => $parentEnquiry->id,
                'full_name' => $data['full_name'] ?? null,
                'mobile' => $data['mobile'] ?? null,
                'email' => $data['email'] ?? null,
                'preferred_destinations' => $data['preferred_destinations'] ?? null,
                'preferred_travelling_date' => $data['preferred_travelling_date'] ?? null,
                'no_of_adults' => $data['no_of_adults'] ?? 0,
                'no_of_children' => $data['no_of_children'] ?? 0,
                'requires_mobility_assistance' => $data['requires_mobility_assistance'] ?? null,
            ]);

            activity()
                ->performedOn($enquiry)
                ->withProperties(['subject_type' => 'GeneralEnquiry', 'subject_id' => $enquiry->id, 'enquiry_id' => $parentEnquiry->id])
                ->log('General enquiry created successfully #'.$enquiry->id);

            return $enquiry;
        });
    }

    public function getForEditShow($id): array
    {
        $enquiry = GeneralEnquiry::with('enquiry')->findOrFail($id);

        return [
            'id' => $enquiry->id,
            'enquiry_id' => $enquiry->enquiry_id,
            'status' => $enquiry->enquiry?->status?->value ?? 'new_lead',
            'status_label' => $enquiry->enquiry?->status?->label() ?? 'New Lead',
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
            $enquiry = GeneralEnquiry::with('enquiry')->findOrFail($id);

            if (! empty($data['preferred_travelling_date'])) {
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

            // Sync parent enquiry common fields
            if ($enquiry->enquiry) {
                $enquiry->enquiry->update([
                    'full_name' => $data['full_name'] ?? $enquiry->full_name,
                    'contact_number' => $data['mobile'] ?? $enquiry->mobile,
                    'email' => $data['email'] ?? $enquiry->email,
                ]);
            }

            $enquiry = $enquiry->fresh();

            activity()
                ->performedOn($enquiry)
                ->withProperties(['subject_type' => 'GeneralEnquiry', 'subject_id' => $enquiry->id, 'enquiry_id' => $enquiry->enquiry_id])
                ->log('General enquiry updated successfully #'.$enquiry->id);

            return $enquiry;
        });
    }

    public function delete($id)
    {
        $enquiry = GeneralEnquiry::find($id);
        if (! $enquiry) {
            return false;
        }

        activity()
            ->performedOn($enquiry)
            ->withProperties(['subject_type' => 'GeneralEnquiry', 'subject_id' => $enquiry->id, 'enquiry_id' => $enquiry->enquiry_id])
            ->log('General enquiry deleted successfully #'.$enquiry->id);

        // Also delete parent enquiry
        if ($enquiry->enquiry_id) {
            Enquiry::where('id', $enquiry->enquiry_id)->delete();
        }

        return $enquiry->delete();
    }
}
