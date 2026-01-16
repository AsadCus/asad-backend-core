<?php

namespace App\Services;

use App\Enums\MaidStatus;
use App\Jobs\AutoRevertInterviewStatusJob;
use App\Jobs\PendingDocumentReminderJob;
use App\Models\Maid;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MaidStatusService
{
    /**
     * Schedule an interview for a maid
     * Changes status from AVAILABLE to INTERVIEWING
     * Sets interview_date, interview_end_date and dispatches auto-revert job
     *
     * @param int $maidId
     * @param string|Carbon $interviewDate
     * @param string|Carbon|null $interviewEndDate
     * @return array
     * @throws \Exception
     */
    public function scheduleInterview(int $maidId, $interviewDate, $interviewEndDate = null): array
    {
        DB::beginTransaction();
        try {
            $maid = Maid::findOrFail($maidId);

            // Validation: Must be in AVAILABLE status
            if ($maid->status !== MaidStatus::AVAILABLE->value) {
                throw new \Exception("Cannot schedule interview. Maid must be in AVAILABLE status. Current status: {$maid->status}");
            }

            // Parse interview start date
            $interviewDateTime = $interviewDate instanceof Carbon
                ? $interviewDate
                : Carbon::parse($interviewDate);

            // Parse interview end date if provided
            $interviewEndDateTime = null;
            if ($interviewEndDate) {
                $interviewEndDateTime = $interviewEndDate instanceof Carbon
                    ? $interviewEndDate
                    : Carbon::parse($interviewEndDate);

                // Validation: End date must be after start date
                if ($interviewEndDateTime->lte($interviewDateTime)) {
                    throw new \Exception("Interview end date must be after start date");
                }
            }

            // Validation: Interview date must be in the future
            if ($interviewDateTime->isPast()) {
                throw new \Exception("Interview date must be in the future");
            }

            // Generate unique job ID
            $jobId = Str::uuid()->toString();

            // Update maid status
            $maid->status = MaidStatus::INTERVIEWING->value;
            $maid->interview_date = $interviewDateTime;
            $maid->interview_end_date = $interviewEndDateTime;
            $maid->status_job_id = $jobId;
            $maid->save();

            // Calculate delay: 1 day (24 hours) after interview_date
            $delayUntil = $interviewDateTime->copy()->addDay();

            // Dispatch auto-revert job
            AutoRevertInterviewStatusJob::dispatch($maidId, $jobId)
                ->delay($delayUntil);

            DB::commit();

            Log::info("MaidStatusService: Interview scheduled", [
                'maid_id' => $maidId,
                'interview_date' => $interviewDateTime->toDateTimeString(),
                'interview_end_date' => $interviewEndDateTime?->toDateTimeString(),
                'job_id' => $jobId,
                'auto_revert_at' => $delayUntil->toDateTimeString()
            ]);

            return [
                'success' => true,
                'message' => 'Interview scheduled successfully',
                'data' => [
                    'maid_id' => $maidId,
                    'status' => $maid->status,
                    'interview_date' => $interviewDateTime->toDateTimeString(),
                    'interview_end_date' => $interviewEndDateTime?->toDateTimeString(),
                    'auto_revert_at' => $delayUntil->toDateTimeString()
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("MaidStatusService: Failed to schedule interview", [
                'maid_id' => $maidId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Complete an interview (success or failed)
     * Success: INTERVIEWING → PENDING (with optional reminder job)
     * Failed: INTERVIEWING → AVAILABLE
     *
     * @param int $maidId
     * @param bool $success
     * @param string|null $handoverDate Handover date (only for success)
     * @param string|null $reason Reason/notes for pending status (only for success)
     * @return array
     * @throws \Exception
     */
    public function completeInterview(int $maidId, bool $success, ?string $handoverDate = null, ?string $reason = null): array
    {
        DB::beginTransaction();
        try {
            $maid = Maid::findOrFail($maidId);

            // Validation: Must be in INTERVIEWING status
            if ($maid->status !== MaidStatus::INTERVIEWING->value) {
                throw new \Exception("Cannot complete interview. Maid must be in INTERVIEWING status. Current status: {$maid->status}");
            }

            if ($success) {
                // Interview successful → PENDING
                $newJobId = Str::uuid()->toString();

                // Use provided handover date or default to 2 days from now
                $pendingUntil = $handoverDate
                    ? Carbon::parse($handoverDate)->endOfDay()
                    : now()->addDays(2);

                $maid->status = MaidStatus::PENDING->value;
                $maid->interview_date = null; // Clear interview date
                $maid->interview_end_date = null; // Clear interview end date
                $maid->pending_until = $pendingUntil;
                $maid->pending_reason = $reason; // Store the reason
                $maid->status_job_id = $newJobId;
                $maid->save();

                // Dispatch auto-assign job to run after pending_until deadline
                \App\Jobs\AutoAssignPendingMaidJob::dispatch($maidId, $newJobId)
                    ->delay($pendingUntil);

                Log::info("MaidStatusService: Auto-assign job scheduled", [
                    'maid_id' => $maidId,
                    'pending_until' => $pendingUntil->toDateTimeString(),
                    'job_id' => $newJobId
                ]);

                $message = 'Interview completed successfully. Maid status changed to PENDING. Will auto-assign in 2 days.';
                $newStatus = MaidStatus::PENDING->value;
            } else {
                // Interview failed → AVAILABLE
                $maid->status = MaidStatus::AVAILABLE->value;
                $maid->interview_date = null;
                $maid->interview_end_date = null;
                $maid->status_job_id = null; // Clear job ID
                $maid->save();

                $message = 'Interview marked as failed. Maid status reverted to AVAILABLE';
                $newStatus = MaidStatus::AVAILABLE->value;
            }

            DB::commit();

            Log::info("MaidStatusService: Interview completed", [
                'maid_id' => $maidId,
                'success' => $success,
                'new_status' => $newStatus
            ]);

            return [
                'success' => true,
                'message' => $message,
                'data' => [
                    'maid_id' => $maidId,
                    'status' => $newStatus,
                    'interview_success' => $success
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("MaidStatusService: Failed to complete interview", [
                'maid_id' => $maidId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Finalize documents
     * Success: PENDING → ASSIGNED
     * Failed: PENDING → AVAILABLE
     *
     * @param int $maidId
     * @param bool $success
     * @return array
     * @throws \Exception
     */
    public function finalizeDocuments(int $maidId, bool $success): array
    {
        DB::beginTransaction();
        try {
            $maid = Maid::findOrFail($maidId);

            // Validation: Must be in PENDING status
            if ($maid->status !== MaidStatus::PENDING->value) {
                throw new \Exception("Cannot finalize documents. Maid must be in PENDING status. Current status: {$maid->status}");
            }

            if ($success) {
                // Document finalization successful → ASSIGNED
                $maid->status = MaidStatus::ASSIGNED->value;
                $maid->status_job_id = null; // Clear job ID as this is terminal state
                $maid->save();

                $message = 'Documents finalized successfully. Maid status changed to ASSIGNED';
                $newStatus = MaidStatus::ASSIGNED->value;
            } else {
                // Document finalization failed → AVAILABLE
                $maid->status = MaidStatus::AVAILABLE->value;
                $maid->status_job_id = null;
                $maid->save();

                $message = 'Document finalization failed. Maid status reverted to AVAILABLE';
                $newStatus = MaidStatus::AVAILABLE->value;
            }

            DB::commit();

            Log::info("MaidStatusService: Documents finalized", [
                'maid_id' => $maidId,
                'success' => $success,
                'new_status' => $newStatus
            ]);

            return [
                'success' => true,
                'message' => $message,
                'data' => [
                    'maid_id' => $maidId,
                    'status' => $newStatus,
                    'finalization_success' => $success
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("MaidStatusService: Failed to finalize documents", [
                'maid_id' => $maidId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Manually update maid status with validation
     * Validates transition rules defined in MaidStatus enum
     *
     * @param int $maidId
     * @param string $newStatus
     * @return array
     * @throws \Exception
     */
    public function updateStatus(int $maidId, string $newStatus): array
    {
        DB::beginTransaction();
        try {
            $maid = Maid::findOrFail($maidId);

            // Validate new status exists
            $newStatusEnum = MaidStatus::tryFrom($newStatus);
            if (!$newStatusEnum) {
                throw new \Exception("Invalid status: {$newStatus}");
            }

            // Get current status enum
            $currentStatusEnum = MaidStatus::from($maid->status);

            // Validate transition
            if (!$currentStatusEnum->canTransitionTo($newStatusEnum)) {
                throw new \Exception(
                    "Invalid status transition from {$maid->status} to {$newStatus}. " .
                        "Please follow the proper workflow."
                );
            }

            // Update status
            $maid->status = $newStatus;

            // Clear job-related fields when manually updating
            if ($newStatus === MaidStatus::AVAILABLE->value || $newStatus === MaidStatus::ASSIGNED->value) {
                $maid->interview_date = null;
                $maid->pending_until = null;
                $maid->pending_reason = null;
                $maid->status_job_id = null;
            }

            $maid->save();

            // If manually moved to ASSIGNED from PENDING, cancel pending auto-assign job
            if ($currentStatusEnum === MaidStatus::PENDING && $newStatusEnum === MaidStatus::ASSIGNED) {
                Log::info("MaidStatusService: Manual assignment before deadline, pending job will be skipped via job_id check", [
                    'maid_id' => $maidId,
                    'previous_job_id' => $maid->status_job_id
                ]);
            }

            DB::commit();

            Log::info("MaidStatusService: Status manually updated", [
                'maid_id' => $maidId,
                'old_status' => $currentStatusEnum->value,
                'new_status' => $newStatus
            ]);

            return [
                'success' => true,
                'message' => "Status updated successfully from {$currentStatusEnum->value} to {$newStatus}",
                'data' => [
                    'maid_id' => $maidId,
                    'old_status' => $currentStatusEnum->value,
                    'new_status' => $newStatus
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("MaidStatusService: Failed to update status", [
                'maid_id' => $maidId,
                'new_status' => $newStatus,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Cancel scheduled interview
     * Reverts status from INTERVIEWING to AVAILABLE
     *
     * @param int $maidId
     * @return array
     * @throws \Exception
     */
    public function cancelInterview(int $maidId): array
    {
        DB::beginTransaction();
        try {
            $maid = Maid::findOrFail($maidId);

            // Validation: Must be in INTERVIEWING status
            if ($maid->status !== MaidStatus::INTERVIEWING->value) {
                throw new \Exception("Cannot cancel interview. Maid must be in INTERVIEWING status. Current status: {$maid->status}");
            }

            // Revert to AVAILABLE
            $maid->status = MaidStatus::AVAILABLE->value;
            $maid->interview_date = null;
            $maid->interview_end_date = null;
            $maid->status_job_id = null; // Clear job ID to prevent auto-revert
            $maid->save();

            DB::commit();

            Log::info("MaidStatusService: Interview cancelled", [
                'maid_id' => $maidId
            ]);

            return [
                'success' => true,
                'message' => 'Interview cancelled successfully. Maid status reverted to AVAILABLE',
                'data' => [
                    'maid_id' => $maidId,
                    'status' => MaidStatus::AVAILABLE->value
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("MaidStatusService: Failed to cancel interview", [
                'maid_id' => $maidId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get maid status history and current job information
     *
     * @param int $maidId
     * @return array
     */
    public function getStatusInfo(int $maidId): array
    {
        $maid = Maid::findOrFail($maidId);

        return [
            'maid_id' => $maidId,
            'current_status' => $maid->status,
            'interview_date' => $maid->interview_date?->toDateTimeString(),
            'status_job_id' => $maid->status_job_id,
            'has_active_job' => !is_null($maid->status_job_id),
            'updated_at' => $maid->updated_at->toDateTimeString()
        ];
    }

    /**
     * Assign maid to customer after deposit payment
     * Changes status from PENDING to ASSIGNED
     *
     * @param int $maidId
     * @param int $customerId
     * @return array
     * @throws \Exception
     */
    public function assignMaidFromPayment(int $maidId, int $customerId): array
    {
        DB::beginTransaction();
        try {
            $maid = Maid::findOrFail($maidId);

            // Only auto-assign if maid is in PENDING status
            if ($maid->status !== MaidStatus::PENDING->value) {
                Log::info("MaidStatusService: Skipping auto-assign, maid not in PENDING status", [
                    'maid_id' => $maidId,
                    'current_status' => $maid->status
                ]);

                return [
                    'success' => false,
                    'message' => "Maid is not in PENDING status. Current status: {$maid->status}",
                    'data' => [
                        'maid_id' => $maidId,
                        'status' => $maid->status
                    ]
                ];
            }

            // Update maid status to ASSIGNED
            $maid->status = MaidStatus::ASSIGNED->value;
            $maid->pending_until = null;
            $maid->status_job_id = null; // Clear job ID as this is terminal state
            $maid->save();

            DB::commit();

            Log::info("MaidStatusService: Maid auto-assigned after deposit payment", [
                'maid_id' => $maidId,
                'customer_id' => $customerId,
                'new_status' => MaidStatus::ASSIGNED->value
            ]);

            return [
                'success' => true,
                'message' => 'Maid automatically assigned to customer after deposit payment',
                'data' => [
                    'maid_id' => $maidId,
                    'customer_id' => $customerId,
                    'status' => MaidStatus::ASSIGNED->value
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("MaidStatusService: Failed to auto-assign maid from payment", [
                'maid_id' => $maidId,
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
