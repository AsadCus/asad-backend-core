<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AttendanceService
{
    /**
     * Resolve the Employee record behind an authenticated user (own check-in / own scope).
     */
    public function resolveEmployee(User $user): Employee
    {
        $employee = $user->employee;

        if (! $employee) {
            abort(422, 'No employee profile is linked to your account.');
        }

        return $employee;
    }

    /**
     * Employee ids the user may read. Null = all employees (view-all).
     *
     * @return array<int>|null
     */
    public function accessibleEmployeeIds(User $user): ?array
    {
        if ($user->can('hris.attendance view-all')) {
            return null;
        }

        if ($user->can('hris.attendance view-team')) {
            $me = $user->employee;
            $ids = $me ? Employee::query()->where('supervisor_id', $me->id)->pluck('id')->all() : [];
            if ($me) {
                $ids[] = $me->id; // a lead sees their own row alongside the team's
            }

            return $ids;
        }

        // view-own / check-in only
        return [$user->employee?->id ?? 0];
    }

    /**
     * @param  array{from?:string,to?:string,employee_id?:int|string,status?:string}  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getForDataTable(User $user, array $filters = []): array
    {
        $query = Attendance::query()->with(['employee.user'])->latest('date');

        $ids = $this->accessibleEmployeeIds($user);
        if ($ids !== null) {
            $query->whereIn('employee_id', $ids);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('date', '<=', $filters['to']);
        }
        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get()->map(fn (Attendance $a) => $this->mapRow($a))->all();
    }

    /**
     * The caller's row for today, plus their lock state — drives the online check-in page.
     *
     * @return array<string, mixed>
     */
    public function todayForUser(User $user): array
    {
        $employee = $this->resolveEmployee($user);
        $today = Carbon::now()->toDateString();

        $row = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        return [
            'date' => $today,
            'locked' => $employee->isAttendanceLocked(),
            'lock_reason' => $employee->attendance_lock_reason,
            'attendance' => $row ? $this->mapRow($row) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetail(User $user, int $id): array
    {
        $attendance = Attendance::query()
            ->with(['employee.user', 'shift', 'checkInBranch', 'checkOutBranch'])
            ->findOrFail($id);

        $ids = $this->accessibleEmployeeIds($user);
        if ($ids !== null && ! in_array($attendance->employee_id, $ids, true)) {
            abort(403, 'You may not view this attendance record.');
        }

        return [
            'id' => $attendance->id,
            'employee_id' => $attendance->employee_id,
            'name' => $attendance->employee?->user?->name ?? $attendance->employee?->employee_no,
            'date' => $attendance->date?->toDateString(),
            'status' => $attendance->status?->label(),
            'status_value' => $attendance->status?->value,
            'late_minutes' => $attendance->late_minutes,
            'early_leave_minutes' => $attendance->early_leave_minutes,
            'work_minutes' => $attendance->work_minutes,
            'shift' => $attendance->shift?->name,
            'check_in' => [
                'at' => $attendance->check_in_at?->format('Y-m-d H:i:s'),
                'lat' => $attendance->check_in_lat,
                'lng' => $attendance->check_in_lng,
                'location' => $attendance->check_in_location,
                'photo_url' => $attendance->check_in_photo_path ? Storage::disk('public')->url($attendance->check_in_photo_path) : null,
                'branch' => $attendance->checkInBranch?->name,
            ],
            'check_out' => [
                'at' => $attendance->check_out_at?->format('Y-m-d H:i:s'),
                'lat' => $attendance->check_out_lat,
                'lng' => $attendance->check_out_lng,
                'location' => $attendance->check_out_location,
                'photo_url' => $attendance->check_out_photo_path ? Storage::disk('public')->url($attendance->check_out_photo_path) : null,
                'branch' => $attendance->checkOutBranch?->name,
            ],
            'notes' => $attendance->notes,
        ];
    }

    /**
     * @param  array{lat:float,lng:float,photo:string,location?:string,branch_id?:int}  $data
     * @return array<string, mixed>
     */
    public function checkIn(User $user, array $data): array
    {
        $employee = $this->resolveEmployee($user);

        if ($employee->isAttendanceLocked()) {
            // 423 Locked — not 403, so the SPA shows a toast instead of the global forbidden redirect.
            throw new HttpException(423, $employee->attendance_lock_reason
                ? "Attendance locked: {$employee->attendance_lock_reason}"
                : 'Your attendance is locked. Please contact HR.');
        }

        if (! $employee->can_check_in) {
            abort(403, 'You are not eligible to check in.');
        }

        $now = Carbon::now();
        $date = $now->toDateString();

        $existing = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->first();

        if ($existing && $existing->check_in_at) {
            abort(422, 'You have already checked in today.');
        }

        $shift = $this->resolveShift($employee, $now);
        $computed = $this->computeCheckInStatus($shift, $now);

        return DB::transaction(function () use ($employee, $existing, $date, $now, $shift, $computed, $data) {
            $path = $this->storeSelfie($data['photo'], $employee->id, $date, 'in');

            $attendance = $existing ?? new Attendance(['employee_id' => $employee->id, 'date' => $date]);
            $attendance->fill([
                'shift_id' => $shift?->id,
                'check_in_at' => $now,
                'check_in_lat' => $data['lat'],
                'check_in_lng' => $data['lng'],
                'check_in_photo_path' => $path,
                'check_in_location' => $data['location'] ?? null,
                'check_in_branch_id' => $data['branch_id'] ?? $employee->branch_id,
                'status' => $computed['status'],
                'late_minutes' => $computed['late_minutes'],
            ]);
            $attendance->save();

            activity()->performedOn($attendance)->log('Attendance check-in #'.$attendance->id);

            return $this->mapRow($attendance);
        });
    }

    /**
     * @param  array{lat:float,lng:float,photo:string,location?:string,branch_id?:int}  $data
     * @return array<string, mixed>
     */
    public function checkOut(User $user, array $data): array
    {
        $employee = $this->resolveEmployee($user);

        if (! $employee->can_check_in) {
            abort(403, 'You are not eligible to check out.');
        }

        $now = Carbon::now();
        $date = $now->toDateString();

        $attendance = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->first();

        if (! $attendance || ! $attendance->check_in_at) {
            abort(422, 'You must check in before checking out.');
        }
        if ($attendance->check_out_at) {
            abort(422, 'You have already checked out today.');
        }

        $shift = $attendance->shift ?? $this->resolveShift($employee, $now);
        [$earlyLeave, $status] = $this->computeCheckOutStatus($shift, $attendance, $now);

        return DB::transaction(function () use ($employee, $attendance, $date, $now, $earlyLeave, $status, $data) {
            $path = $this->storeSelfie($data['photo'], $employee->id, $date, 'out');

            $workMinutes = max(0, intdiv($now->getTimestamp() - $attendance->check_in_at->getTimestamp(), 60));

            $attendance->fill([
                'check_out_at' => $now,
                'check_out_lat' => $data['lat'],
                'check_out_lng' => $data['lng'],
                'check_out_photo_path' => $path,
                'check_out_location' => $data['location'] ?? null,
                'check_out_branch_id' => $data['branch_id'] ?? $employee->branch_id,
                'early_leave_minutes' => $earlyLeave,
                'work_minutes' => $workMinutes,
                'status' => $status,
            ]);
            $attendance->save();

            activity()->performedOn($attendance)->log('Attendance check-out #'.$attendance->id);

            return $this->mapRow($attendance);
        });
    }

    // ---- User lock (HR discipline) -------------------------------------------------------------

    /**
     * Employees with repeated lateness/absence in the last 30 days who are not already locked.
     * ponytail: late-count threshold of 3 + any 'absent' row; refine windows/thresholds if policy needs it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function lockCandidates(): array
    {
        $since = Carbon::now()->subDays(30)->toDateString();

        $rows = Attendance::query()
            ->with('employee.user')
            ->where('date', '>=', $since)
            ->whereIn('status', [AttendanceStatus::Late->value, AttendanceStatus::Absent->value])
            ->get();

        $byEmployee = [];
        foreach ($rows as $row) {
            if ($row->employee?->isAttendanceLocked()) {
                continue;
            }
            $eid = $row->employee_id;
            $byEmployee[$eid] ??= ['employee' => $row->employee, 'late' => [], 'absent' => []];
            $bucket = $row->status === AttendanceStatus::Late ? 'late' : 'absent';
            $byEmployee[$eid][$bucket][] = $row->date?->toDateString();
        }

        $candidates = [];
        foreach ($byEmployee as $eid => $info) {
            $lateCount = count($info['late']);
            $absentCount = count($info['absent']);
            if ($lateCount < 3 && $absentCount < 1) {
                continue;
            }
            $candidates[] = [
                'employee_id' => $eid,
                'employee' => $info['employee']?->user?->name ?? $info['employee']?->employee_no,
                'reason' => $absentCount > 0 ? 'tidak_hadir' : 'terlambat',
                'dates' => array_values(array_unique(array_merge($info['late'], $info['absent']))),
            ];
        }

        return $candidates;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function lockedList(): array
    {
        return Employee::query()
            ->with('user')
            ->whereNotNull('attendance_locked_at')
            ->get()
            ->map(fn (Employee $e) => [
                'employee_id' => $e->id,
                'employee' => $e->user?->name ?? $e->employee_no,
                'reason' => $e->attendance_lock_reason,
                'dates' => $e->attendance_lock_dates ?? [],
                'locked_at' => $e->attendance_locked_at?->format('Y-m-d H:i'),
            ])
            ->all();
    }

    /**
     * @param  array<string>  $dates
     */
    public function lock(int $employeeId, ?string $reason = null, array $dates = []): void
    {
        $employee = Employee::findOrFail($employeeId);
        $employee->update([
            'attendance_locked_at' => Carbon::now(),
            'attendance_lock_reason' => $reason,
            'attendance_lock_dates' => $dates,
        ]);
        activity()->performedOn($employee)->log('Attendance locked #'.$employee->id);
    }

    public function unlock(int $employeeId): void
    {
        $employee = Employee::findOrFail($employeeId);
        $employee->update([
            'attendance_locked_at' => null,
            'attendance_lock_reason' => null,
            'attendance_lock_dates' => null,
        ]);
        activity()->performedOn($employee)->log('Attendance unlocked #'.$employee->id);
    }

    // ---- Bulk import ---------------------------------------------------------------------------

    /**
     * Import attendances from a CSV (header: employee_no, date, check_in, check_out).
     * ponytail: CSV only (no xlsx lib installed); upserts by (employee_no, date).
     *
     * @return array{imported:int, skipped:int, errors:array<int,string>}
     */
    public function import(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            abort(422, 'Could not read the uploaded file.');
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            abort(422, 'The file is empty.');
        }
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $line = 1;

        while (($cols = fgetcsv($handle)) !== false) {
            $line++;
            $rowData = array_combine($header, array_pad($cols, count($header), null));
            $empNo = trim((string) ($rowData['employee_no'] ?? ''));
            $date = trim((string) ($rowData['date'] ?? ''));

            if ($empNo === '' || $date === '') {
                $skipped++;

                continue;
            }

            $employee = Employee::query()->where('employee_no', $empNo)->first();
            if (! $employee) {
                $errors[] = "Line {$line}: unknown employee_no '{$empNo}'";

                continue;
            }

            $checkIn = ! empty($rowData['check_in']) ? Carbon::parse($date.' '.$rowData['check_in']) : null;
            $checkOut = ! empty($rowData['check_out']) ? Carbon::parse($date.' '.$rowData['check_out']) : null;

            $shift = $checkIn ? $this->resolveShift($employee, $checkIn) : null;
            $computed = $checkIn
                ? $this->computeCheckInStatus($shift, $checkIn)
                : ['status' => AttendanceStatus::Absent, 'late_minutes' => 0];

            Attendance::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'date' => $date],
                [
                    'shift_id' => $shift?->id,
                    'check_in_at' => $checkIn,
                    'check_out_at' => $checkOut,
                    'status' => $computed['status'],
                    'late_minutes' => $computed['late_minutes'],
                    'work_minutes' => ($checkIn && $checkOut)
                        ? max(0, intdiv($checkOut->getTimestamp() - $checkIn->getTimestamp(), 60))
                        : 0,
                ],
            );
            $imported++;
        }

        fclose($handle);

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    // ---- Helpers -------------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function mapRow(Attendance $a): array
    {
        return [
            'id' => $a->id,
            'employee_id' => $a->employee_id,
            'name' => $a->employee?->user?->name ?? $a->employee?->employee_no,
            'employee_no' => $a->employee?->employee_no,
            'date' => $a->date?->toDateString(),
            'time_in' => $a->check_in_at?->format('H:i'),
            'time_out' => $a->check_out_at?->format('H:i'),
            'late_minutes' => (int) $a->late_minutes,
            'early_leave_minutes' => (int) $a->early_leave_minutes,
            'work_minutes' => (int) $a->work_minutes,
            'status' => $a->status?->label(),
            'status_value' => $a->status?->value,
        ];
    }

    /**
     * Resolve the employee's shift for a date via their active schedule → that weekday's workday.
     * Returns null when no schedule data is seeded (check-in still succeeds; status = present).
     */
    private function resolveShift(Employee $employee, Carbon $date): ?Shift
    {
        $schedule = $employee->employeeSchedules()
            ->where('effective_from', '<=', $date->toDateString())
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date->toDateString());
            })
            ->latest('effective_from')
            ->with('workSchedule.workScheduleDays.shift')
            ->first();

        if (! $schedule || ! $schedule->workSchedule) {
            return null;
        }

        // Carbon dayOfWeek: 0=Sunday..6=Saturday — matches the work_schedule_days convention.
        $day = $schedule->workSchedule->workScheduleDays
            ->firstWhere('day_of_week', $date->dayOfWeek);

        if (! $day || ! $day->is_workday) {
            return null;
        }

        return $day->shift;
    }

    /**
     * @return array{status: AttendanceStatus, late_minutes: int}
     */
    private function computeCheckInStatus(?Shift $shift, Carbon $checkIn): array
    {
        if (! $shift || ! $shift->start_time) {
            return ['status' => AttendanceStatus::Present, 'late_minutes' => 0];
        }

        $start = Carbon::parse($checkIn->toDateString().' '.$shift->start_time);
        $lateMinutes = max(0, intdiv($checkIn->getTimestamp() - $start->getTimestamp(), 60));
        $tolerance = (int) ($shift->late_tolerance_minutes ?? 0);
        $status = $lateMinutes > $tolerance ? AttendanceStatus::Late : AttendanceStatus::Present;

        return ['status' => $status, 'late_minutes' => $lateMinutes];
    }

    /**
     * @return array{0:int, 1:AttendanceStatus}
     */
    private function computeCheckOutStatus(?Shift $shift, Attendance $attendance, Carbon $checkOut): array
    {
        $earlyLeave = 0;
        if ($shift && $shift->end_time) {
            $end = Carbon::parse($checkOut->toDateString().' '.$shift->end_time);
            $earlyLeave = max(0, intdiv($end->getTimestamp() - $checkOut->getTimestamp(), 60));
        }

        // Late stays the headline; otherwise flag an early leave.
        $status = $attendance->status === AttendanceStatus::Late
            ? AttendanceStatus::Late
            : ($earlyLeave > 0 ? AttendanceStatus::EarlyLeave : AttendanceStatus::Present);

        return [$earlyLeave, $status];
    }

    private function storeSelfie(string $dataUrl, int $employeeId, string $date, string $which): string
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $dataUrl, $m)) {
            $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
            $binary = base64_decode(substr($dataUrl, (int) strpos($dataUrl, ',') + 1));
        } else {
            $ext = 'jpg';
            $binary = base64_decode($dataUrl);
        }

        if ($binary === false) {
            abort(422, 'The selfie image could not be decoded.');
        }

        $path = "attendance/{$employeeId}/{$date}-{$which}.{$ext}";
        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}
