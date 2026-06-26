<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LeaveRequestService
{
    private const APPROVER_LINK = '/approvals';

    private const REQUESTER_LINK = '/requests';

    public function __construct(private HrisNotifier $notifier) {}

    /** Notify the request's requester (their own user). */
    private function notifyRequester(LeaveRequest $leaveRequest, string $title, string $message): void
    {
        $this->notifier->notify($title, $message, self::REQUESTER_LINK, [$leaveRequest->employee?->user_id]);
    }

    /**
     * Leave request ids the user may read. Null = all (view-all).
     *
     * @return array<int>|null
     */
    private function accessibleEmployeeIds(User $user): ?array
    {
        if ($user->can('hris.leave-request view-all')) {
            return null;
        }

        if ($user->can('hris.leave-request view-team')) {
            $me = $user->employee;
            $ids = $me ? Employee::query()->where('supervisor_id', $me->id)->pluck('id')->all() : [];
            if ($me) {
                $ids[] = $me->id;
            }

            return $ids;
        }

        return [$user->employee?->id ?? 0];
    }

    /**
     * @param  array{status?:string}  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getForDataTable(User $user, array $filters = []): array
    {
        $query = LeaveRequest::query()->with(['employee.user', 'leaveType'])->latest('start_date');

        $ids = $this->accessibleEmployeeIds($user);
        if ($ids !== null) {
            $query->whereIn('employee_id', $ids);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get()->map(fn (LeaveRequest $r) => $this->mapRow($r))->all();
    }

    /**
     * The authenticated user's own leave requests, regardless of role.
     *
     * @param  array{status?:string}  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getMyList(User $user, array $filters = []): array
    {
        $query = LeaveRequest::query()
            ->with(['employee.user', 'leaveType'])
            ->where('employee_id', $user->employee?->id ?? 0)
            ->latest('start_date');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get()->map(fn (LeaveRequest $r) => $this->mapRow($r))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetail(User $user, int $id): array
    {
        $leaveRequest = LeaveRequest::query()
            ->with(['employee.user', 'leaveType', 'supervisor.user', 'hrUser'])
            ->findOrFail($id);

        $ids = $this->accessibleEmployeeIds($user);
        if ($ids !== null && ! in_array($leaveRequest->employee_id, $ids, true)) {
            abort(403, 'You may not view this leave request.');
        }

        return $this->mapRow($leaveRequest) + [
            'supervisor' => $leaveRequest->supervisor?->user?->name,
            'supervisor_note' => $leaveRequest->supervisor_note,
            'supervisor_decided_at' => $leaveRequest->supervisor_decided_at?->toIso8601String(),
            'hr' => $leaveRequest->hrUser?->name,
            'hr_note' => $leaveRequest->hr_note,
            'hr_decided_at' => $leaveRequest->hr_decided_at?->toIso8601String(),
        ];
    }

    /**
     * Best-effort lookup of the employee's balance row for this leave type + year.
     * Returns null when no balance has been allocated — callers must treat that as "unbounded".
     */
    private function findBalance(int $employeeId, int $leaveTypeId, int $year): ?LeaveBalance
    {
        return LeaveBalance::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();
    }

    /**
     * Employee submits a leave request → status pending_supervisor.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function store(User $user, array $data, ?UploadedFile $attachment = null): array
    {
        $employee = $user->employee;
        abort_if(! $employee, 422, 'No employee profile is linked to your account.');

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $days = $start->diffInDays($end) + 1;

        return DB::transaction(function () use ($employee, $data, $attachment, $start, $end, $days) {
            $leaveType = LeaveType::findOrFail($data['leave_type_id']);

            if ($leaveType->requires_balance) {
                $balance = $this->findBalance($employee->id, $leaveType->id, $start->year);
                if ($balance && $balance->remaining < $days) {
                    abort(422, 'Insufficient leave balance.');
                }
            }

            $attachmentPath = $attachment?->store("leave-requests/{$employee->id}", 'public');

            $leaveRequest = LeaveRequest::create([
                'request_no' => 'LR-'.Carbon::now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'days' => $days,
                'reason' => $data['reason'],
                'attachment_path' => $attachmentPath,
                'status' => ApprovalStatus::PendingSupervisor,
                'supervisor_id' => $employee->supervisor_id,
            ]);

            activity()->performedOn($leaveRequest)->log('Leave request submitted '.$leaveRequest->request_no);

            $supervisorUserId = $employee->supervisor_id
                ? Employee::query()->whereKey($employee->supervisor_id)->value('user_id')
                : null;
            $this->notifier->notify('Leave request awaiting your approval', $leaveRequest->request_no.' needs your approval.', self::APPROVER_LINK, [$supervisorUserId]);

            return $this->mapRow($leaveRequest->fresh(['employee.user', 'leaveType']));
        });
    }

    /**
     * Supervisor approves: pending_supervisor → pending_hr.
     *
     * @return array<string, mixed>
     */
    public function approve(User $user, int $id, ?string $note): array
    {
        $leaveRequest = LeaveRequest::findOrFail($id);
        abort_unless($leaveRequest->status === ApprovalStatus::PendingSupervisor, 422, 'This leave request is not awaiting supervisor approval.');

        $leaveRequest->update([
            'status' => ApprovalStatus::PendingHr,
            'supervisor_decided_at' => Carbon::now(),
            'supervisor_note' => $note,
        ]);
        activity()->performedOn($leaveRequest)->log('Leave request approved by supervisor '.$leaveRequest->request_no);
        $this->notifier->notify('Leave request awaiting HR verification', $leaveRequest->request_no.' was approved by the supervisor.', self::APPROVER_LINK, [], 'hr');
        $this->notifyRequester($leaveRequest, 'Leave request approved by supervisor', $leaveRequest->request_no.' moved to HR verification.');

        return $this->mapRow($leaveRequest->fresh(['employee.user', 'leaveType']));
    }

    /**
     * HR verifies: pending_hr → approved, and the leave balance (if any) is consumed.
     *
     * @return array<string, mixed>
     */
    public function verify(User $user, int $id, ?string $note): array
    {
        $leaveRequest = LeaveRequest::findOrFail($id);
        abort_unless($leaveRequest->status === ApprovalStatus::PendingHr, 422, 'This leave request is not awaiting HR verification.');

        return DB::transaction(function () use ($leaveRequest, $user, $note) {
            $leaveRequest->update([
                'status' => ApprovalStatus::Approved,
                'hr_user_id' => $user->id,
                'hr_decided_at' => Carbon::now(),
                'hr_note' => $note,
            ]);

            $leaveType = $leaveRequest->leaveType;
            if ($leaveType?->requires_balance) {
                $balance = $this->findBalance($leaveRequest->employee_id, $leaveType->id, $leaveRequest->start_date->year);
                $balance?->increment('used', $leaveRequest->days);
            }

            activity()->performedOn($leaveRequest)->log('Leave request verified by HR '.$leaveRequest->request_no);
            $this->notifyRequester($leaveRequest, 'Leave request approved', $leaveRequest->request_no.' was verified and approved.');

            return $this->mapRow($leaveRequest->fresh(['employee.user', 'leaveType']));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function reject(User $user, int $id, ?string $note): array
    {
        $leaveRequest = LeaveRequest::findOrFail($id);
        abort_unless(
            in_array($leaveRequest->status, [ApprovalStatus::PendingSupervisor, ApprovalStatus::PendingHr], true),
            422,
            'This leave request can no longer be rejected.',
        );

        $isHrStage = $leaveRequest->status === ApprovalStatus::PendingHr;
        $leaveRequest->update([
            'status' => ApprovalStatus::Rejected,
            $isHrStage ? 'hr_note' : 'supervisor_note' => $note,
            $isHrStage ? 'hr_decided_at' : 'supervisor_decided_at' => Carbon::now(),
            ...($isHrStage ? ['hr_user_id' => $user->id] : []),
        ]);
        activity()->performedOn($leaveRequest)->log('Leave request rejected '.$leaveRequest->request_no);
        $this->notifyRequester($leaveRequest, 'Leave request rejected', $leaveRequest->request_no.' was rejected.');

        return $this->mapRow($leaveRequest->fresh(['employee.user', 'leaveType']));
    }

    /**
     * Requester cancels while still pending.
     *
     * @return array<string, mixed>
     */
    public function cancel(User $user, int $id): array
    {
        $leaveRequest = LeaveRequest::findOrFail($id);
        abort_unless($leaveRequest->employee_id === $user->employee?->id, 403, 'You may only cancel your own leave request.');
        abort_unless(
            in_array($leaveRequest->status, [ApprovalStatus::PendingSupervisor, ApprovalStatus::PendingHr], true),
            422,
            'This leave request can no longer be cancelled.',
        );

        $leaveRequest->update(['status' => ApprovalStatus::Cancelled]);
        activity()->performedOn($leaveRequest)->log('Leave request cancelled '.$leaveRequest->request_no);

        return $this->mapRow($leaveRequest->fresh(['employee.user', 'leaveType']));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(LeaveRequest $r): array
    {
        return [
            'id' => $r->id,
            'request_no' => $r->request_no,
            'employee_id' => $r->employee_id,
            'employee' => $r->employee?->user?->name ?? $r->employee?->employee_no,
            'employee_email' => $r->employee?->user?->email,
            'leave_type_id' => $r->leave_type_id,
            'leave_type' => $r->leaveType?->name,
            'start_date' => $r->start_date?->toDateString(),
            'end_date' => $r->end_date?->toDateString(),
            'days' => (float) $r->days,
            'reason' => $r->reason,
            'attachment_url' => $r->attachment_path ? Storage::disk('public')->url($r->attachment_path) : null,
            'status' => $r->status?->label(),
            'status_value' => $r->status?->value,
        ];
    }
}
