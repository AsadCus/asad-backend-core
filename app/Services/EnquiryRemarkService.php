<?php

namespace App\Services;

use App\Enums\EnquiryStatus;
use App\Models\Enquiry;
use App\Models\EnquiryRemark;
use Illuminate\Support\Collection;

class EnquiryRemarkService
{
    /**
     * Get all remarks for an enquiry, eager-loading the creator.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getForEnquiry(int $enquiryId): Collection
    {
        return EnquiryRemark::query()
            ->where('enquiry_id', $enquiryId)
            ->with('creator:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (EnquiryRemark $remark) => [
                'id' => $remark->id,
                'enquiry_id' => $remark->enquiry_id,
                'created_by' => $remark->created_by,
                'creator_name' => $remark->creator?->name ?? 'Unknown',
                'status_at_time' => $remark->status_at_time,
                'remark' => $remark->remark,
                'created_at' => $remark->created_at?->translatedFormat('d M Y, h:i A'),
                'updated_at' => $remark->updated_at?->translatedFormat('d M Y, h:i A'),
            ]);
    }

    /**
     * Store a new remark for an enquiry.
     */
    public function store(int $enquiryId, array $data): EnquiryRemark
    {
        $enquiry = Enquiry::findOrFail($enquiryId);

        if ($enquiry->status !== EnquiryStatus::Confirmed) {
            $enquiry->update([
                'handled_by' => auth()->id(),
            ]);
        }

        return EnquiryRemark::create([
            'enquiry_id' => $enquiryId,
            'created_by' => auth()->id(),
            'status_at_time' => $enquiry->status->value,
            'remark' => $data['remark'],
        ]);
    }

    /**
     * Update an existing remark.
     */
    public function update(int $remarkId, array $data): EnquiryRemark
    {
        $remark = EnquiryRemark::findOrFail($remarkId);
        $remark->update([
            'remark' => $data['remark'],
        ]);

        activity()
                ->performedOn($remark)
                ->withProperties(['subject_type' => 'EnquiryRemark', 'subject_id' => $remark->id ?? null])
                ->log('EnquiryRemark updated successfully #'.($remark->id ?? null));

            return $remark;
    }

    /**
     * Delete a remark.
     */
    public function delete(int $remarkId): void
    {
        EnquiryRemark::findOrFail($remarkId)->delete();
    }
}
