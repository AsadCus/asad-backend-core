<?php

namespace App\Jobs;

use App\Enums\MaidStatus;
use App\Models\Maid;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AutoRevertInterviewStatusJob implements ShouldQueue
{
    use Queueable;

    /**
     * The maid ID to revert status
     */
    protected int $maidId;

    /**
     * The job ID that was assigned to this maid
     */
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
     * Auto-revert maid status from INTERVIEWING to AVAILABLE
     * if 1 day has passed after interview date without manual completion
     */
    public function handle(): void
    {
        try {
            $maid = Maid::find($this->maidId);

            if (!$maid) {
                Log::warning("AutoRevertInterviewStatusJob: Maid not found", [
                    'maid_id' => $this->maidId,
                    'job_id' => $this->jobId
                ]);
                return;
            }

            // Only revert if:
            // 1. Maid is still in INTERVIEWING status
            // 2. The job ID matches (ensures this is the correct job)
            // 3. Interview date + 1 day has passed
            if (
                $maid->status === MaidStatus::INTERVIEWING->value &&
                $maid->status_job_id === $this->jobId &&
                $maid->interview_date &&
                now()->greaterThan($maid->interview_date->copy()->addDay())
            ) {
                $maid->status = MaidStatus::AVAILABLE->value;
                $maid->interview_date = null;
                $maid->interview_end_date = null;
                $maid->status_job_id = null;
                $maid->save();

                Log::info("AutoRevertInterviewStatusJob: Maid status reverted to AVAILABLE", [
                    'maid_id' => $this->maidId,
                    'job_id' => $this->jobId
                ]);
            } else {
                Log::info("AutoRevertInterviewStatusJob: Skipped - Status already changed or job mismatch", [
                    'maid_id' => $this->maidId,
                    'current_status' => $maid->status,
                    'current_job_id' => $maid->status_job_id,
                    'expected_job_id' => $this->jobId
                ]);
            }
        } catch (\Exception $e) {
            Log::error("AutoRevertInterviewStatusJob: Error occurred", [
                'maid_id' => $this->maidId,
                'job_id' => $this->jobId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
