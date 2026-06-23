<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\AttendanceCorrectionType;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttendanceCorrectionService
{
    /**
     * Correction ids the user may read. Null = all (view-all).
     *
     * @return array<int>|null
     */
    private function accessibleEmployeeIds(User $user): ?array
    {
        if ($user->can('hris.attendance-correction view-all')) {
            return null;
        }

        if ($user->can('hris.attendance-correction view-team')) {
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
        $query = AttendanceCorrection::query()->with('employee.user')->latest('date');

        $ids = $this->accessibleEmployeeIds($user);
        if ($ids !== null) {
            $query->whereIn('employee_id', $ids);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get()->map(fn (AttendanceCorrection $c) => $this->mapRow($c))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetail(User $user, int $id): array
    {
        $correction = AttendanceCorrection::query()
            ->with(['employee.user', 'supervisor.user', 'hrUser', 'attendance'])
            ->findOrFail($id);

        $ids = $this->accessibleEmployeeIds($user);
        if ($ids !== null && ! in_array($correction->employee_id, $ids, true)) {
            abort(403, 'You may not view this correction.');
        }

        return $this->mapRow($correction) + [
            'requested_check_in' => $correction->requested_check_in?->format('Y-m-d H:i'),
            'requested_check_out' => $correction->requested_check_out?->format('Y-m-d H:i'),
            'supervisor' => $correction->supervisor?->user?->name,
            'supervisor_note' => $correction->supervisor_note,
            'hr_note' => $correction->hr_note,
        ];
    }

    /**
     * Employee submits a correction → status pending_supervisor.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function store(User $user, array $data, ?UploadedFile $attachment = null): array
    {
        $employee = $user->employee;
        abort_if(! $employee, 422, 'No employee profile is linked to your account.');

        return DB::transaction(function () use ($employee, $data, $attachment) {
            $attachmentPath = $attachment?->store("attendance-corrections/{$employee->id}", 'public');

            $correction = AttendanceCorrection::create([
                'correction_no' => 'COR-'.Carbon::now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                'employee_id' => $employee->id,
                'attendance_id' => $data['attendance_id'] ?? null,
                'date' => $data['date'],
                'correction_type' => $data['correction_type'],
                'requested_check_in' => $data['requested_check_in'] ?? null,
                'requested_check_out' => $data['requested_check_out'] ?? null,
                'reason' => $data['reason'],
                'attachment_path' => $attachmentPath,
                'status' => ApprovalStatus::PendingSupervisor,
                'supervisor_id' => $employee->supervisor_id,
            ]);

            activity()->performedOn($correction)->log('Attendance correction submitted '.$correction->correction_no);

            return $this->mapRow($correction->fresh('employee.user'));
        });
    }

    /**
     * Supervisor approves: pending_supervisor → pending_hr.
     *
     * @return array<string, mixed>
     */
    public function approve(User $user, int $id, ?string $note): array
    {
        $correction = AttendanceCorrection::findOrFail($id);
        abort_unless($correction->status === ApprovalStatus::PendingSupervisor, 422, 'This correction is not awaiting supervisor approval.');

        $correction->update([
            'status' => ApprovalStatus::PendingHr,
            'supervisor_decided_at' => Carbon::now(),
            'supervisor_note' => $note,
        ]);
        activity()->performedOn($correction)->log('Attendance correction approved by supervisor '.$correction->correction_no);

        return $this->mapRow($correction->fresh('employee.user'));
    }

    /**
     * HR verifies: pending_hr → approved, and the change is applied to the attendance row.
     *
     * @return array<string, mixed>
     */
    public function verify(User $user, int $id, ?string $note): array
    {
        $correction = AttendanceCorrection::findOrFail($id);
        abort_unless($correction->status === ApprovalStatus::PendingHr, 422, 'This correction is not awaiting HR verification.');

        return DB::transaction(function () use ($correction, $user, $note) {
            $correction->update([
                'status' => ApprovalStatus::Approved,
                'hr_user_id' => $user->id,
                'hr_decided_at' => Carbon::now(),
                'hr_note' => $note,
            ]);

            $this->applyToAttendance($correction);

            activity()->performedOn($correction)->log('Attendance correction verified by HR '.$correction->correction_no);

            return $this->mapRow($correction->fresh('employee.user'));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function reject(User $user, int $id, ?string $note): array
    {
        $correction = AttendanceCorrection::findOrFail($id);
        abort_unless(
            in_array($correction->status, [ApprovalStatus::PendingSupervisor, ApprovalStatus::PendingHr], true),
            422,
            'This correction can no longer be rejected.',
        );

        $isHrStage = $correction->status === ApprovalStatus::PendingHr;
        $correction->update([
            'status' => ApprovalStatus::Rejected,
            $isHrStage ? 'hr_note' : 'supervisor_note' => $note,
            $isHrStage ? 'hr_decided_at' : 'supervisor_decided_at' => Carbon::now(),
            ...($isHrStage ? ['hr_user_id' => $user->id] : []),
        ]);
        activity()->performedOn($correction)->log('Attendance correction rejected '.$correction->correction_no);

        return $this->mapRow($correction->fresh('employee.user'));
    }

    /**
     * Requester cancels while still pending.
     *
     * @return array<string, mixed>
     */
    public function cancel(User $user, int $id): array
    {
        $correction = AttendanceCorrection::findOrFail($id);
        abort_unless($correction->employee_id === $user->employee?->id, 403, 'You may only cancel your own correction.');
        abort_unless(
            in_array($correction->status, [ApprovalStatus::PendingSupervisor, ApprovalStatus::PendingHr], true),
            422,
            'This correction can no longer be cancelled.',
        );

        $correction->update(['status' => ApprovalStatus::Cancelled]);
        activity()->performedOn($correction)->log('Attendance correction cancelled '.$correction->correction_no);

        return $this->mapRow($correction->fresh('employee.user'));
    }

    /**
     * Apply an approved correction to the underlying attendance row.
     * ponytail: punch corrections write the requested time; soft types (wfh/visit/sick/…) just
     * ensure a present row exists for the day. Status recompute beyond late-clearing is deferred.
     */
    private function applyToAttendance(AttendanceCorrection $correction): void
    {
        $attendance = $correction->attendance
            ?? Attendance::firstOrNew([
                'employee_id' => $correction->employee_id,
                'date' => $correction->date->toDateString(),
            ]);

        switch ($correction->correction_type) {
            case AttendanceCorrectionType::MissedCheckIn:
                if ($correction->requested_check_in) {
                    $attendance->check_in_at = $correction->requested_check_in;
                    $attendance->late_minutes = 0; // corrected punch clears the unexcused lateness
                    $attendance->status = AttendanceStatus::Present;
                }
                break;
            case AttendanceCorrectionType::MissedCheckOut:
                if ($correction->requested_check_out) {
                    $attendance->check_out_at = $correction->requested_check_out;
                    if ($attendance->check_in_at) {
                        $attendance->work_minutes = max(0, intdiv(
                            $correction->requested_check_out->getTimestamp() - $attendance->check_in_at->getTimestamp(),
                            60,
                        ));
                    }
                }
                break;
            default:
                $attendance->status = AttendanceStatus::Present;
                break;
        }

        $attendance->notes = trim(($attendance->notes ? $attendance->notes."\n" : '')
            ."Corrected via {$correction->correction_no}: {$correction->reason}");
        $attendance->save();

        $correction->attendance_id = $attendance->id;
        $correction->saveQuietly();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(AttendanceCorrection $c): array
    {
        return [
            'id' => $c->id,
            'correction_no' => $c->correction_no,
            'employee_id' => $c->employee_id,
            'employee' => $c->employee?->user?->name ?? $c->employee?->employee_no,
            'date' => $c->date?->toDateString(),
            'correction_type' => $c->correction_type?->label(),
            'correction_type_value' => $c->correction_type?->value,
            'reason' => $c->reason,
            'attachment_url' => $c->attachment_path ? Storage::disk('public')->url($c->attachment_path) : null,
            'status' => $c->status?->label(),
            'status_value' => $c->status?->value,
        ];
    }
}
