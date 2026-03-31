<?php

namespace App\Services;

use App\Enums\EnquiryStatus;
use App\Models\Enquiry;
use App\Models\GeneralEnquiry;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\DataScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GeneralEnquiryService
{
    public function __construct(private NumberingService $numberingService) {}

    public function getForDataTable(array $filters = [])
    {
        $data = GeneralEnquiry::query()
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
            ->map(function ($generalEnquiry) {
                return [
                    'id' => $generalEnquiry->id,
                    'enquiry_id' => $generalEnquiry->enquiry_id,
                    'status' => $generalEnquiry->enquiry?->status?->value ?? 'new_lead',
                    'status_label' => $generalEnquiry->enquiry?->status?->label() ?? 'New Lead',
                    'enquiry_number' => $generalEnquiry->enquiry?->enquiry_number,
                    'name' => $generalEnquiry->enquiry?->name,
                    'contact_number' => $generalEnquiry->enquiry?->contact_number,
                    'email' => $generalEnquiry->enquiry?->email,
                    'package_id' => $generalEnquiry->enquiry?->package_id,
                    'preferred_destinations' => $generalEnquiry->preferred_destinations,
                    'preferred_travelling_date' => $generalEnquiry->preferred_travelling_date_formatted,
                    'no_of_adults' => $generalEnquiry->no_of_adults,
                    'no_of_children' => $generalEnquiry->no_of_children,
                    'requires_mobility_assistance' => $generalEnquiry->requires_mobility_assistance,
                    'last_remark' => $generalEnquiry->enquiry?->latestRemark->remark ?? '-',
                    'handled_by_name' => $generalEnquiry->enquiry?->handledBy?->name ?? '-',
                    'created_at' => $generalEnquiry->created_at?->translatedFormat('d F Y'),
                    'updated_at' => $generalEnquiry->updated_at?->translatedFormat('d F Y'),
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
                'enquiry_number' => $this->numberingService->ensureNumber(
                    'general_enquiry',
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

            $generalEnquiry = GeneralEnquiry::create([
                'enquiry_id' => $parentEnquiry->id,
                'preferred_destinations' => $data['preferred_destinations'] ?? null,
                'preferred_travelling_date' => $data['preferred_travelling_date'] ?? null,
                'no_of_adults' => $data['no_of_adults'] ?? 0,
                'no_of_children' => $data['no_of_children'] ?? 0,
                'requires_mobility_assistance' => $data['requires_mobility_assistance'] ?? null,
            ]);

            activity()
                ->performedOn($generalEnquiry)
                ->withProperties(['subject_type' => 'GeneralEnquiry', 'subject_id' => $generalEnquiry->id, 'enquiry_id' => $parentEnquiry->id])
                ->log('General enquiry created successfully #'.$generalEnquiry->id);

            // Create notification for admin/sales users
            $this->createEnquiryNotification($generalEnquiry, $parentEnquiry);

            return $generalEnquiry;
        });
    }

    public function getForEditShow($id): array
    {
        $query = GeneralEnquiry::with('enquiry');

        if (DataScope::shouldScopeSalesEnquiries()) {
            $query->whereHas('enquiry', function ($enquiryQuery) {
                $enquiryQuery->where(function ($visibilityQuery) {
                    $visibilityQuery
                        ->where('handled_by', auth()->id())
                        ->orWhereNull('handled_by');
                });
            });
        }

        $generalEnquiry = $query->findOrFail($id);

        return [
            'id' => $generalEnquiry->id,
            'enquiry_id' => $generalEnquiry->enquiry_id,
            'status' => $generalEnquiry->enquiry?->status?->value ?? 'new_lead',
            'status_label' => $generalEnquiry->enquiry?->status?->label() ?? 'New Lead',
            'enquiry_number' => $generalEnquiry->enquiry?->enquiry_number,
            'name' => $generalEnquiry->enquiry?->name,
            'contact_number' => $generalEnquiry->enquiry?->contact_number,
            'email' => $generalEnquiry->enquiry?->email,
            'preferred_destinations' => $generalEnquiry->preferred_destinations,
            'preferred_travelling_date' => $generalEnquiry->preferred_travelling_date_formatted,
            'no_of_adults' => $generalEnquiry->no_of_adults,
            'no_of_children' => $generalEnquiry->no_of_children,
            'requires_mobility_assistance' => $generalEnquiry->requires_mobility_assistance,
            'package_id' => $generalEnquiry->enquiry?->package_id,
            'created_at' => $generalEnquiry->created_at?->translatedFormat('d F Y'),
            'updated_at' => $generalEnquiry->updated_at?->translatedFormat('d F Y'),
        ];
    }

    public function update(array $data, int $id): GeneralEnquiry
    {
        return DB::transaction(function () use ($data, $id) {
            $generalEnquiry = GeneralEnquiry::with('enquiry')->findOrFail($id);

            if (! empty($data['preferred_travelling_date'])) {
                $data['preferred_travelling_date'] = Carbon::parse($data['preferred_travelling_date'])->format('Y-m-d');
            }

            $generalEnquiry->update([
                'preferred_destinations' => $data['preferred_destinations'] ?? $generalEnquiry->preferred_destinations,
                'preferred_travelling_date' => $data['preferred_travelling_date'] ?? $generalEnquiry->preferred_travelling_date,
                'no_of_adults' => $data['no_of_adults'] ?? $generalEnquiry->no_of_adults,
                'no_of_children' => $data['no_of_children'] ?? $generalEnquiry->no_of_children,
                'requires_mobility_assistance' => $data['requires_mobility_assistance'] ?? $generalEnquiry->requires_mobility_assistance,
            ]);

            // Sync parent enquiry common fields
            if ($generalEnquiry->enquiry) {
                $generalEnquiry->enquiry->update([
                    'enquiry_number' => array_key_exists('enquiry_number', $data)
                        ? $this->numberingService->ensureNumber(
                            'general_enquiry',
                            $data['enquiry_number'],
                            (int) $generalEnquiry->enquiry->id,
                            isset($data['number_format_id']) ? (int) $data['number_format_id'] : null,
                        )
                        : $generalEnquiry->enquiry?->enquiry_number,
                    'name' => $data['name'] ?? $generalEnquiry->enquiry?->name,
                    'contact_number' => $data['contact_number'] ?? $generalEnquiry->enquiry?->contact_number,
                    'email' => $data['email'] ?? $generalEnquiry->enquiry?->email,
                ]);
            }

            $generalEnquiry = $generalEnquiry->fresh();

            activity()
                ->performedOn($generalEnquiry)
                ->withProperties(['subject_type' => 'GeneralEnquiry', 'subject_id' => $generalEnquiry->id, 'enquiry_id' => $generalEnquiry->enquiry_id])
                ->log('General enquiry updated successfully #'.$generalEnquiry->id);

            return $generalEnquiry;
        });
    }

    public function delete($id)
    {
        $generalEnquiry = GeneralEnquiry::find($id);
        if (! $generalEnquiry) {
            return false;
        }

        activity()
            ->performedOn($generalEnquiry)
            ->withProperties(['subject_type' => 'GeneralEnquiry', 'subject_id' => $generalEnquiry->id, 'enquiry_id' => $generalEnquiry->enquiry_id])
            ->log('General enquiry deleted successfully #'.$generalEnquiry->id);

        // Also delete parent enquiry
        if ($generalEnquiry->enquiry_id) {
            Enquiry::where('id', $generalEnquiry->enquiry_id)->delete();
        }

        return $generalEnquiry->delete();
    }

    /**
     * Create notification for admin/sales users about new enquiry.
     */
    private function createEnquiryNotification(GeneralEnquiry $generalEnquiry, Enquiry $parentEnquiry): void
    {
        try {
            $adminAndSalesUsers = User::role(['admin', 'sales'])->get();

            if ($adminAndSalesUsers->isEmpty()) {
                return;
            }

            $notification = Notification::create([
                'title' => "New General Enquiry from {$generalEnquiry->name}",
                'message' => 'A new General enquiry has been received. Please review.',
                'link' => "/general-enquiries/{$generalEnquiry->id}",
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
