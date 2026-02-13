<?php

namespace App\Services;

use App\Models\GeneralEnquiry;
use App\Models\PrivateEnquiry;

class EnquiryService
{
    public function __construct(
        public GeneralEnquiryService $generalEnquiryService,
        public PrivateEnquiryService $privateEnquiryService,
    ) {}

    /**
     * Get all enquiries (general + private) for the datatable.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getForDataTable(array $filters = []): array
    {
        $generalEnquiries = GeneralEnquiry::query()
            ->when($filters['from_date'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to_date'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
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
                    'type' => 'General',
                    'full_name' => $enquiry->full_name,
                    'contact' => $enquiry->mobile,
                    'email' => $enquiry->email,
                    'created_at' => $enquiry->created_at?->translatedFormat('d F Y'),
                ];
            });

        $privateEnquiries = PrivateEnquiry::query()
            ->when($filters['from_date'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to_date'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
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
                    'type' => 'Private',
                    'full_name' => $enquiry->full_name,
                    'contact' => $enquiry->contact_number,
                    'email' => $enquiry->email,
                    'created_at' => $enquiry->created_at?->translatedFormat('d F Y'),
                ];
            });

        return $generalEnquiries->merge($privateEnquiries)
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }

    /**
     * Get summary counts for dashboard widgets.
     *
     * @return array{total: int, general: int, private: int}
     */
    public function getSummaryCounts(): array
    {
        return [
            'total' => GeneralEnquiry::count() + PrivateEnquiry::count(),
            'general' => GeneralEnquiry::count(),
            'private' => PrivateEnquiry::count(),
        ];
    }
}
