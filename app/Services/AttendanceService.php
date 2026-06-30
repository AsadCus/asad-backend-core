<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\OrgUnit;
use App\Models\Shift;
use App\Models\User;
use App\Models\WfhVisitRequest;
use App\Support\AttendancePunch;
use App\Support\Geo;
use App\Support\HrisScope;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AttendanceService
{
    public function __construct(
        private EmployeeScheduleResolver $scheduleResolver,
        private WfhVisitLookupService $wfhVisitLookup,
    ) {}

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
        $query = Attendance::query()->with(['employee.user', 'shift'])->latest('date');

        $ids = $this->accessibleEmployeeIds($user);
        if ($ids !== null) {
            // view-own / view-team — already limited to the right employees; no org bound.
            $query->whereIn('employee_id', $ids);
        } else {
            // view-all — bound to the active org-unit subtree (the org switcher narrows it).
            HrisScope::applyViaEmployee($query, 'employee', $user);
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
     * One row per employee per calendar day in [from, to] — the report's "headline" status
     * is resolved with the same precedence as {@see resolveDayStatus()}: Holiday > Cuti
     * (approved leave) > WFH/Visit (approved request) > the day's actual check-in status >
     * Weekend (scheduled rest day) > Alpha (a working day with none of the above). Unlike
     * {@see getForDataTable()}, this also surfaces days with no Attendance row at all, so
     * leave/rest/absence are visible rather than silently missing from the report.
     *
     * @param  array{employee_id?:int|string, status?:string}  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getDailyReport(User $user, ?string $from, ?string $to, array $filters = []): array
    {
        $from = $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->startOfMonth();
        $to = $to ? Carbon::parse($to)->startOfDay() : Carbon::now()->endOfMonth();
        abort_if($from->gt($to), 422, 'The "from" date must not be after "to".');

        $employees = $this->reportableEmployees($user, $filters);
        if ($employees->isEmpty()) {
            return [];
        }
        $employeeIds = $employees->pluck('id')->all();

        $attendanceByKey = Attendance::query()
            ->with('shift')
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->get()
            ->keyBy(fn (Attendance $a) => $this->dayKey($a->employee_id, $a->date));

        // whereDate(), not where() — date-cast columns can carry a 00:00:00 time component
        // depending on the DB driver, so a plain string comparison against a date-only value
        // is unreliable. whereDate() normalizes both sides to just the calendar date.
        $leaveDateKeys = $this->expandRangesToDayKeys(
            LeaveRequest::query()->whereIn('employee_id', $employeeIds)->where('status', ApprovalStatus::Approved)
                ->whereDate('start_date', '<=', $to->toDateString())->whereDate('end_date', '>=', $from->toDateString())
                ->get(['employee_id', 'start_date', 'end_date']),
            $from, $to,
        );

        $wfhVisitByKey = $this->wfhVisitLookup->approvedTypeByDayKey($employeeIds, $from, $to);

        $holidayKeys = Holiday::query()
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->flip();

        // Preloaded once — classifying every employee over the whole range would otherwise cost
        // one schedule query per employee per day.
        $schedulesByEmployee = $this->scheduleResolver->preloadForRange($employeeIds, $from, $to);

        $rows = [];
        foreach ($employees as $employee) {
            $orgBreakdown = $employee->orgUnitBreakdown();

            foreach (CarbonPeriod::create($from, $to) as $date) {
                $key = $this->dayKey($employee->id, $date);
                $attendance = $attendanceByKey->get($key);

                $day = $this->scheduleResolver->resolveDayFromBatch($schedulesByEmployee, $employee->id, $date);
                $status = AttendanceStatus::classify(
                    isHoliday: $holidayKeys->has($date->toDateString()),
                    isOnLeave: $leaveDateKeys->has($key),
                    wfhVisitType: $wfhVisitByKey->get($key),
                    attendanceStatus: $attendance?->status,
                    isRestDay: (bool) ($day && ! $day->is_workday),
                );

                if (! empty($filters['status']) && $status->value !== $filters['status']) {
                    continue;
                }

                // The day's shift, whether or not they actually checked in — falls back to the
                // scheduled shift so a Cuti/Alpha/WFH day still shows what hours applied.
                $shift = $attendance?->shift ?? $day?->shift;

                $rows[] = [
                    // Null when the day has no underlying Attendance row (Cuti/WFH-without-a-punch/
                    // Alpha/Weekend) — callers use this to tell a viewable record from a synthesized one.
                    'id' => $attendance?->id,
                    'employee_id' => $employee->id,
                    'name' => $employee->user?->name ?? $employee->employee_no,
                    'employee_no' => $employee->employee_no,
                    'nik' => $employee->nik,
                    'business_unit' => $orgBreakdown['business_unit']?->name,
                    'branch' => $orgBreakdown['branch']?->name,
                    'department' => $orgBreakdown['department']?->name,
                    'division' => $orgBreakdown['division']?->name,
                    'date' => $date->toDateString(),
                    'shift' => $shift?->name,
                    'shift_start_time' => $shift?->start_time,
                    'shift_end_time' => $shift?->end_time,
                    'time_in' => $attendance?->check_in_at?->toIso8601String(),
                    'time_out' => $attendance?->check_out_at?->toIso8601String(),
                    'check_in_location' => $attendance?->check_in_location,
                    'check_out_location' => $attendance?->check_out_location,
                    'late_minutes' => (int) ($attendance?->late_minutes ?? 0),
                    'early_leave_minutes' => (int) ($attendance?->early_leave_minutes ?? 0),
                    'work_minutes' => (int) ($attendance?->work_minutes ?? 0),
                    'status' => $status->label(),
                    'status_value' => $status->value,
                ];
            }
        }

        return $rows;
    }

    /** Employees the user may see in the report, narrowed to a single one when filtered. */
    private function reportableEmployees(User $user, array $filters)
    {
        $ids = $this->accessibleEmployeeIds($user);

        return Employee::query()
            // The org tree is shallow — eager-load the whole ancestor chain once so
            // Employee::orgUnitBreakdown() below never triggers a query per employee.
            ->with(['user', 'orgUnit.parent.parent.parent.parent'])
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->when($ids === null, fn ($q) => HrisScope::apply($q, 'org_unit_id', $user))
            ->when(! empty($filters['employee_id']), fn ($q) => $q->where('id', $filters['employee_id']))
            ->get();
    }

    private function dayKey(int $employeeId, Carbon|string $date): string
    {
        $date = $date instanceof Carbon ? $date->toDateString() : $date;

        return "{$employeeId}:{$date}";
    }

    /**
     * @param  \Illuminate\Support\Collection<int, LeaveRequest>  $requests
     * @return \Illuminate\Support\Collection<string, true>
     */
    private function expandRangesToDayKeys($requests, Carbon $from, Carbon $to)
    {
        $keys = collect();
        foreach ($requests as $request) {
            $this->eachDayInOverlap($request->employee_id, $request->start_date, $request->end_date, $from, $to,
                fn (string $key) => $keys->put($key, true));
        }

        return $keys;
    }

    private function eachDayInOverlap(int $employeeId, Carbon $start, Carbon $end, Carbon $from, Carbon $to, callable $onDay): void
    {
        $rangeStart = $start->greaterThan($from) ? $start->copy() : $from->copy();
        $rangeEnd = $end->lessThan($to) ? $end->copy() : $to->copy();

        foreach (CarbonPeriod::create($rangeStart, $rangeEnd) as $date) {
            $onDay($this->dayKey($employeeId, $date));
        }
    }

    /**
     * The caller's row for today, plus their lock state — drives the online check-in page.
     *
     * @return array<string, mixed>
     */
    public function todayForUser(User $user): array
    {
        $employee = $this->resolveEmployee($user);
        $today = Carbon::now();

        $row = Attendance::query()
            ->with(['sessions', 'shift'])
            ->where('employee_id', $employee->id)
            ->whereDate('date', $today->toDateString())
            ->first();

        $shift = $row?->shift ?? $this->resolveShift($employee, $today);

        return [
            'date' => $today->toDateString(),
            'locked' => $employee->isAttendanceLocked(),
            'lock_reason' => $employee->attendance_lock_reason,
            // `open` = there is a session checked-in but not yet checked-out → show Check Out.
            'open' => (bool) ($row && $row->sessions->whereNull('check_out_at')->isNotEmpty()),
            'shift' => $shift?->toCardArray(),
            'attendance' => $row ? $this->mapRow($row) : null,
            'work_location' => $this->mapWorkLocation($employee->resolveWorkLocation()),
        ] + $this->wfhVisitStatus($employee);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetail(User $user, int $id): array
    {
        $attendance = Attendance::query()
            ->with([
                'employee.user', 'shift', 'checkInBranch', 'checkOutBranch',
                'sessions.checkInBranch', 'sessions.checkOutBranch',
            ])
            ->findOrFail($id);

        $ids = $this->accessibleEmployeeIds($user);
        if ($ids !== null && ! in_array($attendance->employee_id, $ids, true)) {
            abort(403, 'You may not view this attendance record.');
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

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
            // The employee's current resolved geofence, so the detail map can draw the same
            // office anchor + in/out radius circle as the live check-in screen (see
            // mapWorkLocation()/getToday()). This reflects today's org-unit placement, not
            // necessarily what was configured on the punch date.
            'work_location' => $this->mapWorkLocation($attendance->employee?->resolveWorkLocation()),
            'check_in' => [
                'at' => $attendance->check_in_at?->toIso8601String(),
                'lat' => $attendance->check_in_lat,
                'lng' => $attendance->check_in_lng,
                'location' => $attendance->check_in_location,
                'photo_url' => $attendance->check_in_photo_path ? $disk->url($attendance->check_in_photo_path) : null,
                'branch' => $attendance->checkInBranch?->name,
            ],
            'check_out' => [
                'at' => $attendance->check_out_at?->toIso8601String(),
                'lat' => $attendance->check_out_lat,
                'lng' => $attendance->check_out_lng,
                'location' => $attendance->check_out_location,
                'photo_url' => $attendance->check_out_photo_path ? $disk->url($attendance->check_out_photo_path) : null,
                'branch' => $attendance->checkOutBranch?->name,
            ],
            // Per-session breakdown (the top-level check_in/check_out above stay the daily summary).
            'sessions' => $attendance->sessions->map(fn (AttendanceSession $s): array => [
                'check_in' => [
                    'at' => $s->check_in_at?->toIso8601String(),
                    'lat' => $s->check_in_lat,
                    'lng' => $s->check_in_lng,
                    'location' => $s->check_in_location,
                    'photo_url' => $s->check_in_photo_path ? $disk->url($s->check_in_photo_path) : null,
                    'branch' => $s->checkInBranch?->name,
                ],
                'check_out' => [
                    'at' => $s->check_out_at?->toIso8601String(),
                    'lat' => $s->check_out_lat,
                    'lng' => $s->check_out_lng,
                    'location' => $s->check_out_location,
                    'photo_url' => $s->check_out_photo_path ? $disk->url($s->check_out_photo_path) : null,
                    'branch' => $s->checkOutBranch?->name,
                ],
            ])->all(),
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
        $activeWfhVisit = $this->wfhVisitLookup->approvedForDate($employee, $now);
        $this->assertWithinGeofence($employee, (float) $data['lat'], (float) $data['lng'], $activeWfhVisit);

        $date = $now->toDateString();

        $attendance = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->first();

        if ($attendance && $attendance->openSession()) {
            abort(422, 'You have an open session. Check out first.');
        }

        $shift = $this->resolveShift($employee, $now);

        return DB::transaction(function () use ($employee, $attendance, $date, $now, $shift, $data) {
            $path = $this->storeSelfie($data['photo'], $employee->id, $date, 'in-'.$now->format('His'));

            $attendance = $attendance ?? new Attendance(['employee_id' => $employee->id, 'date' => $date]);
            $attendance->shift_id ??= $shift?->id;

            // First check-in of the day sets the headline status/lateness; later sessions don't.
            if (! $attendance->check_in_at) {
                $computed = AttendancePunch::checkInStatus($shift, $now);
                $attendance->status = $computed['status'];
                $attendance->late_minutes = $computed['late_minutes'];
            }
            $attendance->save();

            $attendance->sessions()->create([
                'check_in_at' => $now,
                'check_in_lat' => $data['lat'],
                'check_in_lng' => $data['lng'],
                'check_in_photo_path' => $path,
                'check_in_location' => $data['location'] ?? null,
                'check_in_branch_id' => $data['branch_id'] ?? $employee->branch_id,
            ]);

            $this->recomputeSummary($attendance);

            activity()->performedOn($attendance)->log('Attendance check-in #'.$attendance->id);

            return $this->mapRow($attendance->fresh());
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
        $activeWfhVisit = $this->wfhVisitLookup->approvedForDate($employee, $now);
        $this->assertWithinGeofence($employee, (float) $data['lat'], (float) $data['lng'], $activeWfhVisit);

        $date = $now->toDateString();

        $attendance = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->first();

        $session = $attendance?->openSession();
        if (! $session) {
            abort(422, 'You must check in before checking out.');
        }

        $shift = $attendance->shift ?? $this->resolveShift($employee, $now);

        return DB::transaction(function () use ($employee, $attendance, $session, $date, $now, $shift, $data) {
            $path = $this->storeSelfie($data['photo'], $employee->id, $date, 'out-'.$now->format('His'));

            $session->fill([
                'check_out_at' => $now,
                'check_out_lat' => $data['lat'],
                'check_out_lng' => $data['lng'],
                'check_out_photo_path' => $path,
                'check_out_location' => $data['location'] ?? null,
                'check_out_branch_id' => $data['branch_id'] ?? $employee->branch_id,
            ])->save();

            $this->recomputeSummary($attendance);

            // ponytail: early-leave/status reflects the latest checkout, so between a lunch
            // checkout and the next check-in the day can read "Early Leave" — it settles on the
            // final checkout. Not worth detecting "is this the last checkout of the day".
            [$earlyLeave, $status] = AttendancePunch::checkOutStatus($shift, $attendance, $now);
            $attendance->early_leave_minutes = $earlyLeave;
            $attendance->status = $status;
            $attendance->save();

            activity()->performedOn($attendance)->log('Attendance check-out #'.$attendance->id);

            return $this->mapRow($attendance->fresh());
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
                'locked_at' => $e->attendance_locked_at?->toIso8601String(),
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
            $checkOut = null;
            if (! empty($rowData['check_out'])) {
                $checkOut = Carbon::parse($date.' '.$rowData['check_out']);
                // Overnight shift: checkout time earlier than check-in means it's the next day.
                if ($checkIn && $checkOut->lt($checkIn)) {
                    $checkOut->addDay();
                }
            }

            $shift = $checkIn ? $this->resolveShift($employee, $checkIn) : null;
            $computed = $checkIn
                ? AttendancePunch::checkInStatus($shift, $checkIn)
                : ['status' => $this->resolveDayStatus($employee, Carbon::parse($date)), 'late_minutes' => 0];

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
     * Reject a punch made outside the employee's allowed location for the day.
     *
     * Normally that's the resolved office geofence ({@see OrgUnit::resolveLocation()} walks
     * the org tree up from the employee's placement to the nearest ancestor with its own
     * lat/lng/radius configured). An approved WFH day — or a Field Visit left "open" —
     * waives the office radius entirely: the whole point of WFH/open-visit is that the
     * employee isn't expected to be anywhere near the office. A "locked" visit instead
     * checks against that visit's own declared pin/radius.
     */
    private function assertWithinGeofence(
        Employee $employee,
        float $lat,
        float $lng,
        ?WfhVisitRequest $activeWfhVisit,
    ): void {
        if ($activeWfhVisit) {
            $this->assertWithinWfhVisitGeofence($activeWfhVisit, $lat, $lng);

            return;
        }

        $location = $employee->resolveWorkLocation();
        $radius = $location?->geofence_radius_meters;

        if (! $location || ! $location->has_location || $location->latitude === null
            || $location->longitude === null || ! $radius) {
            return;
        }

        $this->assertWithinRadius($lat, $lng, (float) $location->latitude, (float) $location->longitude, (int) $radius, $location->name);
    }

    /** WFH and "open" visits have no location restriction; "locked" visits use their own pin. */
    private function assertWithinWfhVisitGeofence(WfhVisitRequest $visit, float $lat, float $lng): void
    {
        $isLocked = $visit->type === 'visit' && $visit->geotag_mode === 'locked';

        if (! $isLocked || $visit->location_lat === null || $visit->location_lng === null) {
            return;
        }

        $this->assertWithinRadius(
            $lat, $lng, (float) $visit->location_lat, (float) $visit->location_lng,
            (int) ($visit->location_radius ?? 100), $visit->location_address ?? 'the visit location',
        );
    }

    private function assertWithinRadius(float $lat, float $lng, float $targetLat, float $targetLng, int $radius, string $label): void
    {
        $distance = Geo::distanceMeters($targetLat, $targetLng, $lat, $lng);

        if ($distance > $radius) {
            abort(422, sprintf(
                'You are %dm away from %s, outside the allowed %dm radius. Move closer and try again.',
                round($distance),
                $label,
                $radius,
            ));
        }
    }

    /**
     * Returns active_wfh_visit (fully approved today) and pending_wfh_visit (still pending today).
     *
     * @return array{active_wfh_visit: array<string,mixed>|null, pending_wfh_visit: array<string,mixed>|null}
     */
    private function wfhVisitStatus(Employee $employee): array
    {
        $today = Carbon::today();
        $approved = $this->wfhVisitLookup->approvedForDate($employee, $today);

        $pending = WfhVisitRequest::query()
            ->where('employee_id', $employee->id)
            ->whereDate('start_date', '<=', $today->toDateString())
            ->whereDate('end_date', '>=', $today->toDateString())
            ->whereIn('status', [ApprovalStatus::PendingSupervisor, ApprovalStatus::PendingHr])
            ->first();

        return [
            'active_wfh_visit' => $approved ? [
                'type' => $approved->type,
                'geotag_mode' => $approved->geotag_mode ?? ($approved->type === 'wfh' ? null : 'open'),
                'location_address' => $approved->location_address,
                'location_lat' => $approved->location_lat,
                'location_lng' => $approved->location_lng,
                'location_radius' => $approved->location_radius,
            ] : null,
            'pending_wfh_visit' => $pending ? [
                'type' => $pending->type,
                'location_address' => $pending->location_address,
            ] : null,
        ];
    }

    /**
     * @return array{id:int, name:string, latitude:float, longitude:float, radius_meters:int}|null
     */
    private function mapWorkLocation(?OrgUnit $location): ?array
    {
        if (! $location || ! $location->has_location || $location->latitude === null || $location->longitude === null) {
            return null;
        }

        return [
            'id' => $location->id,
            'name' => $location->name,
            'latitude' => (float) $location->latitude,
            'longitude' => (float) $location->longitude,
            'radius_meters' => (int) ($location->geofence_radius_meters ?? 0),
        ];
    }

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
            'shift' => $a->shift?->name,
            'time_in' => $a->check_in_at?->toIso8601String(),
            'time_out' => $a->check_out_at?->toIso8601String(),
            'late_minutes' => (int) $a->late_minutes,
            'early_leave_minutes' => (int) $a->early_leave_minutes,
            'work_minutes' => (int) $a->work_minutes,
            'status' => $a->status?->label(),
            'status_value' => $a->status?->value,
        ];
    }

    /**
     * Resolve the employee's shift for a date via their active schedule → that weekday's workday.
     * Returns null when there is no schedule or the day is a rest day (check-in still succeeds).
     */
    private function resolveShift(Employee $employee, Carbon $date): ?Shift
    {
        $day = $this->scheduleResolver->resolveDay($employee, $date);

        return $day && $day->is_workday ? $day->shift : null;
    }

    /**
     * Classify a date for an employee with no check-in that day, via
     * {@see AttendanceStatus::classify()} — the same precedence the report uses.
     */
    private function resolveDayStatus(Employee $employee, Carbon $date): AttendanceStatus
    {
        $day = $this->scheduleResolver->resolveDay($employee, $date);

        return AttendanceStatus::classify(
            isHoliday: Holiday::isHoliday($date),
            isOnLeave: LeaveRequest::approvedOnDate($employee->id, $date),
            wfhVisitType: $this->wfhVisitLookup->approvedForDate($employee, $date)?->type,
            attendanceStatus: null,
            isRestDay: (bool) ($day && ! $day->is_workday),
        );
    }

    /**
     * Roll the day's sessions up into the parent summary: the parent mirrors the first
     * check-in punch and the last check-out punch (null while a session is still open), plus
     * total worked minutes across closed sessions. Keeps the summary row a faithful snapshot
     * so the index/detail views work without joining sessions.
     */
    private function recomputeSummary(Attendance $attendance): void
    {
        $sessions = $attendance->sessions()->get();
        $first = $sessions->sortBy('check_in_at')->first();
        $closed = $sessions->whereNotNull('check_out_at');
        $hasOpen = $sessions->whereNull('check_out_at')->isNotEmpty();
        $last = $hasOpen ? null : $closed->sortBy('check_out_at')->last();

        $attendance->fill([
            'check_in_at' => $first?->check_in_at,
            'check_in_lat' => $first?->check_in_lat,
            'check_in_lng' => $first?->check_in_lng,
            'check_in_photo_path' => $first?->check_in_photo_path,
            'check_in_location' => $first?->check_in_location,
            'check_in_branch_id' => $first?->check_in_branch_id,
            'check_out_at' => $last?->check_out_at,
            'check_out_lat' => $last?->check_out_lat,
            'check_out_lng' => $last?->check_out_lng,
            'check_out_photo_path' => $last?->check_out_photo_path,
            'check_out_location' => $last?->check_out_location,
            'check_out_branch_id' => $last?->check_out_branch_id,
            'work_minutes' => (int) $closed->sum(
                fn (AttendanceSession $s): int => max(0, intdiv(
                    $s->check_out_at->getTimestamp() - $s->check_in_at->getTimestamp(), 60
                ))
            ),
        ]);
        $attendance->save();
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
