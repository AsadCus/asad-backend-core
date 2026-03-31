<?php

namespace App\Services;

use App\Enums\EnquiryStatus;
use App\Models\Enquiry;
use App\Models\Notification;
use App\Models\PrivateEnquiry;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\DataScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PrivateEnquiryService
{
    public function __construct(private NumberingService $numberingService) {}

    public function getForDataTable(array $filters = [])
    {
        $data = PrivateEnquiry::query()
            ->with(['enquiry.latestRemark', 'enquiry.handledBy:id,name'])
            ->when(DataScope::shouldScopeSalesEnquiries(), function ($query) {
                $query->whereHas('enquiry', function ($enquiryQuery) {
                    $enquiryQuery->where(function ($visibilityQuery) {
                        $visibilityQuery
                            ->where('handled_by', auth()->id())
                            ->orWhereNull('handled_by');
                    });
                });
            })
            ->when($filters['from_date'] ?? null, function ($q, $value) {
                $q->whereDate('created_at', '>=', $value);
            })
            ->when($filters['to_date'] ?? null, function ($q, $value) {
                $q->whereDate('created_at', '<=', $value);
            })
            ->when($filters['search'] ?? null, function ($q, $value) {
                $q->where(function ($query) use ($value) {
                    $query->where('name', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%")
                        ->orWhere('contact_number', 'like', "%{$value}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($privateEnquiry) {
                return [
                    'id' => $privateEnquiry->id,
                    'enquiry_id' => $privateEnquiry->enquiry_id,
                    'status' => $privateEnquiry->enquiry?->status?->value ?? 'new_lead',
                    'status_label' => $privateEnquiry->enquiry?->status?->label() ?? 'New Lead',
                    'enquiry_number' => $privateEnquiry->enquiry?->enquiry_number,
                    'name' => $privateEnquiry->enquiry?->name,
                    'contact_number' => $privateEnquiry->enquiry?->contact_number,
                    'email' => $privateEnquiry->enquiry?->email,
                    'passport_expiry_date' => $privateEnquiry->passport_expiry_date_formatted,
                    'departure_date' => $privateEnquiry->departure_date_formatted,
                    'return_date' => $privateEnquiry->return_date_formatted,
                    'no_of_pax' => $privateEnquiry->no_of_pax,
                    'no_of_children' => $privateEnquiry->no_of_children,
                    'airline' => $privateEnquiry->airline,
                    'class' => $privateEnquiry->class,
                    'require_mutawif' => $privateEnquiry->require_mutawif,
                    'require_umrah_course' => $privateEnquiry->require_umrah_course,
                    'require_umrah_official' => $privateEnquiry->require_umrah_official,
                    'makkah_or_madinah_first' => $privateEnquiry->makkah_or_madinah_first,
                    'no_of_nights_makkah' => $privateEnquiry->no_of_nights_makkah,
                    'hotel_makkah' => $privateEnquiry->hotel_makkah,
                    'meals_makkah' => $privateEnquiry->meals_makkah,
                    'no_of_nights_madinah' => $privateEnquiry->no_of_nights_madinah,
                    'hotel_madinah' => $privateEnquiry->hotel_madinah,
                    'meals_madinah' => $privateEnquiry->meals_madinah,
                    'land_transfer' => $privateEnquiry->land_transfer,
                    'add_on_speed_train' => $privateEnquiry->add_on_speed_train,
                    'require_meet_greet' => $privateEnquiry->require_meet_greet,
                    'require_mutawiffah_ustazah_rawdah' => $privateEnquiry->require_mutawiffah_ustazah_rawdah,
                    'madinah_tour_with_mutawif' => $privateEnquiry->madinah_tour_with_mutawif,
                    'makkah_tour_with_mutawif' => $privateEnquiry->makkah_tour_with_mutawif,
                    'has_chronic_disease' => $privateEnquiry->has_chronic_disease,
                    'chronic_disease_details' => $privateEnquiry->chronic_disease_details,
                    'need_wheelchair' => $privateEnquiry->need_wheelchair,
                    'other_remarks' => $privateEnquiry->other_remarks,
                    'last_remark' => $privateEnquiry->enquiry?->latestRemark->remark ?? '-',
                    'handled_by_name' => $privateEnquiry->enquiry?->handledBy?->name ?? '-',
                    'created_at' => $privateEnquiry->created_at?->translatedFormat('d F Y'),
                    'updated_at' => $privateEnquiry->updated_at?->translatedFormat('d F Y'),
                ];
            });

        return $data;
    }

    public function store(array $data = []): PrivateEnquiry
    {
        return DB::transaction(function () use ($data) {
            // Format date fields if present
            foreach (
                [
                    'passport_expiry_date',
                    'departure_date',
                    'return_date',
                ] as $dateField
            ) {
                if (! empty($data[$dateField])) {
                    $data[$dateField] = Carbon::parse($data[$dateField])->format('Y-m-d');
                }
            }

            // Create parent enquiry record
            $parentEnquiry = Enquiry::create([
                'type' => 'private',
                'enquiry_number' => $this->numberingService->ensureNumber(
                    'private_enquiry',
                    $data['enquiry_number'] ?? null,
                    null,
                    isset($data['number_format_id']) ? (int) $data['number_format_id'] : null,
                ),
                'status' => EnquiryStatus::NewLead->value,
                'name' => $data['name'] ?? '',
                'contact_number' => $data['contact_number'] ?? '',
                'email' => $data['email'] ?? '',
                'created_by' => auth()->id(),
            ]);

            $privateEnquiry = PrivateEnquiry::create([
                'enquiry_id' => $parentEnquiry->id,
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
                ->performedOn($privateEnquiry)
                ->withProperties(['subject_type' => 'PrivateEnquiry', 'subject_id' => $privateEnquiry->id, 'enquiry_id' => $parentEnquiry->id])
                ->log('Private enquiry created successfully #'.$privateEnquiry->id);

            // Create notification for admin/sales users
            $this->createEnquiryNotification($privateEnquiry, $parentEnquiry);

            return $privateEnquiry;
        });
    }

    public function getForEditShow($id): array
    {
        $query = PrivateEnquiry::with('enquiry');

        if (DataScope::shouldScopeSalesEnquiries()) {
            $query->whereHas('enquiry', function ($enquiryQuery) {
                $enquiryQuery->where(function ($visibilityQuery) {
                    $visibilityQuery
                        ->where('handled_by', auth()->id())
                        ->orWhereNull('handled_by');
                });
            });
        }

        $privateEnquiry = $query->findOrFail($id);

        return [
            'id' => $privateEnquiry->id,
            'enquiry_id' => $privateEnquiry->enquiry_id,
            'status' => $privateEnquiry->enquiry?->status?->value ?? 'new_lead',
            'status_label' => $privateEnquiry->enquiry?->status?->label() ?? 'New Lead',
            'enquiry_number' => $privateEnquiry->enquiry?->enquiry_number,
            'name' => $privateEnquiry->enquiry?->name,
            'contact_number' => $privateEnquiry->enquiry?->contact_number,
            'email' => $privateEnquiry->enquiry?->email,
            'passport_expiry_date' => $privateEnquiry->passport_expiry_date_formatted,
            'departure_date' => $privateEnquiry->departure_date_formatted,
            'return_date' => $privateEnquiry->return_date_formatted,
            'no_of_pax' => $privateEnquiry->no_of_pax,
            'no_of_children' => $privateEnquiry->no_of_children,
            'airline' => $privateEnquiry->airline,
            'class' => $privateEnquiry->class,
            'require_mutawif' => $privateEnquiry->require_mutawif,
            'require_umrah_course' => $privateEnquiry->require_umrah_course,
            'require_umrah_official' => $privateEnquiry->require_umrah_official,
            'makkah_or_madinah_first' => $privateEnquiry->makkah_or_madinah_first,
            'no_of_nights_makkah' => $privateEnquiry->no_of_nights_makkah,
            'hotel_makkah' => $privateEnquiry->hotel_makkah,
            'meals_makkah' => $privateEnquiry->meals_makkah,
            'no_of_nights_madinah' => $privateEnquiry->no_of_nights_madinah,
            'hotel_madinah' => $privateEnquiry->hotel_madinah,
            'meals_madinah' => $privateEnquiry->meals_madinah,
            'land_transfer' => $privateEnquiry->land_transfer,
            'add_on_speed_train' => $privateEnquiry->add_on_speed_train,
            'require_meet_greet' => $privateEnquiry->require_meet_greet,
            'require_mutawiffah_ustazah_rawdah' => $privateEnquiry->require_mutawiffah_ustazah_rawdah,
            'madinah_tour_with_mutawif' => $privateEnquiry->madinah_tour_with_mutawif,
            'makkah_tour_with_mutawif' => $privateEnquiry->makkah_tour_with_mutawif,
            'has_chronic_disease' => $privateEnquiry->has_chronic_disease,
            'chronic_disease_details' => $privateEnquiry->chronic_disease_details,
            'need_wheelchair' => $privateEnquiry->need_wheelchair,
            'other_remarks' => $privateEnquiry->other_remarks,
            'created_at' => $privateEnquiry->created_at?->translatedFormat('d F Y'),
            'updated_at' => $privateEnquiry->updated_at?->translatedFormat('d F Y'),
        ];
    }

    public function update(array $data, int $id): PrivateEnquiry
    {
        return DB::transaction(function () use ($data, $id) {
            $privateEnquiry = PrivateEnquiry::with('enquiry')->findOrFail($id);

            foreach (
                [
                    'passport_expiry_date',
                    'departure_date',
                    'return_date',
                ] as $dateField
            ) {
                if (! empty($data[$dateField])) {
                    $data[$dateField] = Carbon::parse($data[$dateField])->format('Y-m-d');
                }
            }

            $privateEnquiry->update([
                'passport_expiry_date' => $data['passport_expiry_date'] ?? $privateEnquiry->passport_expiry_date,
                'departure_date' => $data['departure_date'] ?? $privateEnquiry->departure_date,
                'return_date' => $data['return_date'] ?? $privateEnquiry->return_date,
                'no_of_pax' => $data['no_of_pax'] ?? $privateEnquiry->no_of_pax,
                'no_of_children' => $data['no_of_children'] ?? $privateEnquiry->no_of_children,
                'airline' => $data['airline'] ?? $privateEnquiry->airline,
                'class' => $data['class'] ?? $privateEnquiry->class,
                'require_mutawif' => $data['require_mutawif'] ?? $privateEnquiry->require_mutawif,
                'require_umrah_course' => $data['require_umrah_course'] ?? $privateEnquiry->require_umrah_course,
                'require_umrah_official' => $data['require_umrah_official'] ?? $privateEnquiry->require_umrah_official,
                'makkah_or_madinah_first' => $data['makkah_or_madinah_first'] ?? $privateEnquiry->makkah_or_madinah_first,
                'no_of_nights_makkah' => $data['no_of_nights_makkah'] ?? $privateEnquiry->no_of_nights_makkah,
                'hotel_makkah' => $data['hotel_makkah'] ?? $privateEnquiry->hotel_makkah,
                'meals_makkah' => $data['meals_makkah'] ?? $privateEnquiry->meals_makkah,
                'no_of_nights_madinah' => $data['no_of_nights_madinah'] ?? $privateEnquiry->no_of_nights_madinah,
                'hotel_madinah' => $data['hotel_madinah'] ?? $privateEnquiry->hotel_madinah,
                'meals_madinah' => $data['meals_madinah'] ?? $privateEnquiry->meals_madinah,
                'land_transfer' => $data['land_transfer'] ?? $privateEnquiry->land_transfer,
                'add_on_speed_train' => $data['add_on_speed_train'] ?? $privateEnquiry->add_on_speed_train,
                'require_meet_greet' => $data['require_meet_greet'] ?? $privateEnquiry->require_meet_greet,
                'require_mutawiffah_ustazah_rawdah' => $data['require_mutawiffah_ustazah_rawdah'] ?? $privateEnquiry->require_mutawiffah_ustazah_rawdah,
                'madinah_tour_with_mutawif' => $data['madinah_tour_with_mutawif'] ?? $privateEnquiry->madinah_tour_with_mutawif,
                'makkah_tour_with_mutawif' => $data['makkah_tour_with_mutawif'] ?? $privateEnquiry->makkah_tour_with_mutawif,
                'has_chronic_disease' => $data['has_chronic_disease'] ?? $privateEnquiry->has_chronic_disease,
                'chronic_disease_details' => $data['chronic_disease_details'] ?? $privateEnquiry->chronic_disease_details,
                'need_wheelchair' => $data['need_wheelchair'] ?? $privateEnquiry->need_wheelchair,
                'other_remarks' => $data['other_remarks'] ?? $privateEnquiry->other_remarks,
            ]);

            // Sync parent enquiry common fields
            if ($privateEnquiry->enquiry) {
                $privateEnquiry->enquiry->update([
                    'enquiry_number' => array_key_exists('enquiry_number', $data)
                        ? $this->numberingService->ensureNumber(
                            'private_enquiry',
                            $data['enquiry_number'],
                            (int) $privateEnquiry->enquiry->id,
                            isset($data['number_format_id']) ? (int) $data['number_format_id'] : null,
                        )
                        : $privateEnquiry->enquiry?->enquiry_number,
                    'name' => $data['name'] ?? $privateEnquiry->enquiry?->name,
                    'contact_number' => $data['contact_number'] ?? $privateEnquiry->enquiry?->contact_number,
                    'email' => $data['email'] ?? $privateEnquiry->enquiry?->email,
                ]);
            }

            $privateEnquiry = $privateEnquiry->fresh();

            activity()
                ->performedOn($privateEnquiry)
                ->withProperties(['subject_type' => 'PrivateEnquiry', 'subject_id' => $privateEnquiry->id, 'enquiry_id' => $privateEnquiry->enquiry_id])
                ->log('Private enquiry updated successfully #'.$privateEnquiry->id);

            return $privateEnquiry;
        });
    }

    public function delete($id)
    {
        $privateEnquiry = PrivateEnquiry::find($id);
        if (! $privateEnquiry) {
            return false;
        }

        activity()
            ->performedOn($privateEnquiry)
            ->withProperties(['subject_type' => 'PrivateEnquiry', 'subject_id' => $privateEnquiry->id, 'enquiry_id' => $privateEnquiry->enquiry_id])
            ->log('Private enquiry deleted successfully #'.$privateEnquiry->id);

        // Also delete parent enquiry
        if ($privateEnquiry->enquiry_id) {
            Enquiry::where('id', $privateEnquiry->enquiry_id)->delete();
        }

        return $privateEnquiry->delete();
    }

    /**
     * Create notification for admin/sales users about new enquiry.
     */
    private function createEnquiryNotification(PrivateEnquiry $privateEnquiry, Enquiry $parentEnquiry): void
    {
        try {
            $adminAndSalesUsers = User::role(['admin', 'sales'])->get();

            if ($adminAndSalesUsers->isEmpty()) {
                return;
            }

            $notification = Notification::create([
                'title' => "New Private Enquiry from {$privateEnquiry->name}",
                'message' => 'A new Private enquiry has been received. Please review.',
                'link' => "/private-enquiries/{$privateEnquiry->id}",
                'type' => 'info',
            ]);

            foreach ($adminAndSalesUsers as $user) {
                UserNotification::create([
                    'user_id' => $user->id,
                    'notification_id' => $notification->id,
                    'is_read' => false,
                ]);
            }
        } catch (\Exception $e) {
            // Silently fail if roles don't exist (e.g., in tests)
            // The enquiry creation itself should not fail due to notification issues
        }
    }
}
