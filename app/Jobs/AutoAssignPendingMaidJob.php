<?php

namespace App\Jobs;

use App\Enums\MaidStatus;
use App\Models\Maid;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AutoAssignPendingMaidJob implements ShouldQueue
{
    use Queueable;

    protected int $maidId;
    protected string $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $maidId, string $jobId)
    {
        $this->maidId = $maidId;
        $this->jobId = $jobId;
    }

    /**
     * Execute the job.
     * Auto-assign maid from PENDING to ASSIGNED after pending_until date
     */
    public function handle(): void
    {
        try {
            $maid = Maid::find($this->maidId);

            if (!$maid) {
                Log::warning("AutoAssignPendingMaidJob: Maid not found", [
                    'maid_id' => $this->maidId,
                    'job_id' => $this->jobId
                ]);
                return;
            }

            // Validate job ID matches (prevent stale jobs from executing)
            if ($maid->status_job_id !== $this->jobId) {
                Log::info("AutoAssignPendingMaidJob: Job ID mismatch, skipping", [
                    'maid_id' => $this->maidId,
                    'expected_job_id' => $this->jobId,
                    'current_job_id' => $maid->status_job_id
                ]);
                return;
            }

            // Only proceed if still in PENDING status
            if ($maid->status !== MaidStatus::PENDING->value) {
                Log::info("AutoAssignPendingMaidJob: Maid no longer in PENDING status", [
                    'maid_id' => $this->maidId,
                    'current_status' => $maid->status
                ]);
                return;
            }

            // Check if pending_until has passed
            if ($maid->pending_until && now()->greaterThan($maid->pending_until)) {
                // Auto-assign to ASSIGNED
                $maid->status = MaidStatus::ASSIGNED->value;
                $maid->pending_until = null;
                $maid->status_job_id = null;
                $maid->save();

                Log::info("AutoAssignPendingMaidJob: Maid auto-assigned", [
                    'maid_id' => $this->maidId,
                    'previous_status' => MaidStatus::PENDING->value,
                    'new_status' => MaidStatus::ASSIGNED->value
                ]);
            } else {
                Log::info("AutoAssignPendingMaidJob: pending_until not yet reached", [
                    'maid_id' => $this->maidId,
                    'pending_until' => $maid->pending_until?->toDateTimeString(),
                    'now' => now()->toDateTimeString()
                ]);
            }
        } catch (\Exception $e) {
            Log::error("AutoAssignPendingMaidJob: Failed to auto-assign maid", [
                'maid_id' => $this->maidId,
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
