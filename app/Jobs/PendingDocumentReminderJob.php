<?php

namespace App\Jobs;

use App\Enums\MaidStatus;
use App\Models\Maid;
use App\Notifications\PendingDocumentReminder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class PendingDocumentReminderJob implements ShouldQueue
{
    use Queueable;

    /**
     * The maid ID to send reminder for
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
     * Send reminder notification if maid is still in PENDING status
     * after expected duration (3 days)
     */
    public function handle(): void
    {
        try {
            $maid = Maid::find($this->maidId);

            if (!$maid) {
                Log::warning("PendingDocumentReminderJob: Maid not found", [
                    'maid_id' => $this->maidId,
                    'job_id' => $this->jobId
                ]);
                return;
            }

            // Only send reminder if:
            // 1. Maid is still in PENDING status
            // 2. The job ID matches (ensures this is the correct job)
            if (
                $maid->status === MaidStatus::PENDING->value &&
                $maid->status_job_id === $this->jobId
            ) {
                // TODO: Implement notification logic
                // You can send email, create notification, or log the reminder
                // Example: Notification::send($admins, new PendingDocumentReminder($maid));
                
                Log::info("PendingDocumentReminderJob: Reminder sent for pending documents", [
                    'maid_id' => $this->maidId,
                    'maid_name' => $maid->name,
                    'job_id' => $this->jobId,
                    'days_pending' => now()->diffInDays($maid->updated_at)
                ]);

                // Optional: You can create a notification record in your system
                // or send email to admins here
            } else {
                Log::info("PendingDocumentReminderJob: Skipped - Status already changed or job mismatch", [
                    'maid_id' => $this->maidId,
                    'current_status' => $maid->status,
                    'current_job_id' => $maid->status_job_id,
                    'expected_job_id' => $this->jobId
                ]);
            }
        } catch (\Exception $e) {
            Log::error("PendingDocumentReminderJob: Error occurred", [
                'maid_id' => $this->maidId,
                'job_id' => $this->jobId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
