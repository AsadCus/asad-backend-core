<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Models\Employee;
use App\Models\User;
use App\Models\WfhVisitRequest;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WfhVisitRequestService
{
    private const APPROVER_LINK = '/approvals';

    private const REQUESTER_LINK = '/requests';

    public function __construct(
        private HrisNotifier $notifier,
        private WorkingDaysCalculator $workingDays,
    ) {}

    /**
     * @return array<int>|null null = view-all access
     */
    private function accessibleEmployeeIds(User $user): ?array
    {
        if ($user->can('hris.wfh-visit-request view-all')) {
            return null;
        }

        if ($user->can('hris.wfh-visit-request view-team')) {
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
     * @param  array{status?:string, type?:string, from?:string, to?:string}  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getForDataTable(User $user, array $filters = []): array
    {
        $query = WfhVisitRequest::query()->with(['employee.user', 'attachments'])->latest('start_date');

        $ids = $this->accessibleEmployeeIds($user);
        if ($ids !== null) {
            $query->whereIn('employee_id', $ids);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['from'])) {
            $query->where('start_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('end_date', '<=', $filters['to']);
        }

        return $query->get()->map(fn (WfhVisitRequest $r) => $this->mapRow($r))->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMyList(User $user, array $filters = []): array
    {
        $employeeId = $user->employee?->id ?? 0;

        $query = WfhVisitRequest::query()
            ->with(['employee.user', 'attachments'])
            ->where('employee_id', $employeeId)
            ->latest('start_date');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->get()->map(fn (WfhVisitRequest $r) => $this->mapRow($r))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetail(User $user, int $id): array
    {
        $req = WfhVisitRequest::query()
            ->with(['employee.user', 'supervisor.user', 'hrUser', 'attachments'])
            ->findOrFail($id);

        $ids = $this->accessibleEmployeeIds($user);
        if ($ids !== null && ! in_array($req->employee_id, $ids, true)) {
            abort(403, 'You may not view this request.');
        }

        return $this->mapRow($req) + [
            'supervisor' => $req->supervisor?->user?->name,
            'supervisor_note' => $req->supervisor_note,
            'supervisor_decided_at' => $req->supervisor_decided_at?->toIso8601String(),
            'hr' => $req->hrUser?->name,
            'hr_note' => $req->hr_note,
            'hr_decided_at' => $req->hr_decided_at?->toIso8601String(),
            'employee_signature_url' => $req->employee?->user?->signature_url,
            'supervisor_signature_url' => $req->supervisor_decided_at ? $req->supervisor?->user?->signature_url : null,
            'hr_signature_url' => $req->hr_decided_at ? $req->hrUser?->signature_url : null,
        ];
    }

    /**
     * Employee submits a WFH/Visit request.
     *
     * @param  array<string, mixed>  $data
     * @param  UploadedFile[]  $attachments
     * @return array<string, mixed>
     */
    public function store(User $user, array $data, array $attachments = []): array
    {
        $employee = $user->employee;
        abort_if(! $employee, 422, 'No employee profile is linked to your account.');

        return DB::transaction(function () use ($employee, $data, $attachments) {
            $startDate = Carbon::parse($data['start_date']);
            $endDate = Carbon::parse($data['end_date']);

            $this->assertNoOverlap($employee->id, $startDate, $endDate);

            $totalDays = $this->workingDays->countWorkingDays($employee, $startDate, $endDate);
            abort_if($totalDays < 1, 422, 'The selected date range has no working days — pick a range that includes at least one.');

            // Resolve geotag_mode for visit type
            $geotagMode = null;
            if ($data['type'] === 'visit') {
                $hasPin = ! empty($data['location_lat']) && ! empty($data['location_lng']);
                $geotagMode = $hasPin ? 'locked' : 'open';
            }

            $req = WfhVisitRequest::create([
                'request_no' => 'WFH-'.Carbon::now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                'employee_id' => $employee->id,
                'type' => $data['type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'total_days' => $totalDays,
                'reason' => $data['reason'],
                'location_address' => $data['location_address'] ?? null,
                'location_lat' => $data['location_lat'] ?? null,
                'location_lng' => $data['location_lng'] ?? null,
                'location_radius' => $data['location_radius'] ?? null,
                'geotag_mode' => $geotagMode,
                'status' => ApprovalStatus::PendingSupervisor,
                'supervisor_id' => $employee->supervisor_id,
            ]);

            $this->saveAttachments($req, $attachments, 'submission', $employee->user_id);

            activity()->performedOn($req)->log('WFH/Visit request submitted '.$req->request_no);

            $supervisorUserId = $employee->supervisor_id
                ? Employee::query()->whereKey($employee->supervisor_id)->value('user_id')
                : null;

            $employeeName = $employee->user?->name ?? $employee->employee_no;
            $this->notifier->notify(
                'Pengajuan WFH/Visit Menunggu Persetujuan Anda',
                "{$employeeName} mengajukan {$this->summary($req)}. Mohon segera ditinjau.",
                self::APPROVER_LINK,
                [$supervisorUserId],
                null,
                'info',
            );

            return $this->mapRow($req->fresh(['employee.user', 'attachments']));
        });
    }

    /**
     * Block a submission whose date range overlaps one of the employee's own requests that's
     * still "active" (pending or approved) — rejected/cancelled ones are free to resubmit over.
     * Runs inside store()'s transaction so a duplicate can't slip in via a race.
     */
    private function assertNoOverlap(int $employeeId, Carbon $startDate, Carbon $endDate): void
    {
        $conflict = WfhVisitRequest::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', [
                ApprovalStatus::PendingSupervisor,
                ApprovalStatus::PendingHr,
                ApprovalStatus::Approved,
            ])
            ->where('start_date', '<=', $endDate->toDateString())
            ->where('end_date', '>=', $startDate->toDateString())
            ->first();

        if (! $conflict) {
            return;
        }

        $typeLabel = $conflict->type === 'wfh' ? 'WFH' : 'Visit';
        $range = $conflict->start_date->isSameDay($conflict->end_date)
            ? $conflict->start_date->format('d M Y')
            : $conflict->start_date->format('d M Y').' – '.$conflict->end_date->format('d M Y');

        abort(422, "You already have a {$conflict->status->label()} {$typeLabel} request ({$conflict->request_no}) covering {$range}. Cancel it first or pick different dates.");
    }

    /**
     * Supervisor approves: pending_supervisor → pending_hr.
     *
     * @param  UploadedFile[]  $attachments
     * @return array<string, mixed>
     */
    public function approve(User $user, int $id, ?string $note, array $attachments = []): array
    {
        $req = WfhVisitRequest::findOrFail($id);
        abort_unless($req->status === ApprovalStatus::PendingSupervisor, 422, 'Not awaiting supervisor approval.');

        $req->update([
            'status' => ApprovalStatus::PendingHr,
            'supervisor_decided_at' => Carbon::now(),
            'supervisor_note' => $note,
        ]);
        $this->saveAttachments($req, $attachments, 'supervisor', $user->id);
        activity()->performedOn($req)->log('WFH/Visit approved by supervisor '.$req->request_no);

        $employeeName = $req->employee?->user?->name ?? $req->employee?->employee_no ?? 'Karyawan';
        $supervisorName = $user->name;

        // Notify HR team to verify
        $this->notifier->notify(
            'Pengajuan WFH/Visit Siap Diverifikasi HR',
            "{$req->request_no} — {$this->summary($req)} atas nama {$employeeName} telah disetujui oleh atasan ({$supervisorName}). Silakan diverifikasi.",
            self::APPROVER_LINK,
            [],
            'hr',
            'info',
        );

        // Notify requester their request moved forward
        $this->notifyRequester(
            $req,
            'Pengajuan WFH/Visit Disetujui Atasan',
            "Pengajuan {$req->request_no} ({$this->summary($req)}) Anda telah disetujui oleh {$supervisorName} dan diteruskan ke HR untuk verifikasi akhir.",
        );

        return $this->mapRow($req->fresh(['employee.user', 'attachments']));
    }

    /**
     * HR verifies: pending_hr → approved.
     *
     * @param  UploadedFile[]  $attachments
     * @return array<string, mixed>
     */
    public function verify(User $user, int $id, ?string $note, array $attachments = []): array
    {
        $req = WfhVisitRequest::findOrFail($id);
        abort_unless($req->status === ApprovalStatus::PendingHr, 422, 'Not awaiting HR verification.');

        $req->update([
            'status' => ApprovalStatus::Approved,
            'hr_user_id' => $user->id,
            'hr_decided_at' => Carbon::now(),
            'hr_note' => $note,
        ]);
        $this->saveAttachments($req, $attachments, 'hr', $user->id);
        activity()->performedOn($req)->log('WFH/Visit verified by HR '.$req->request_no);

        $hrName = $user->name;
        $this->notifyRequester(
            $req,
            'Pengajuan WFH/Visit Disetujui & Aktif',
            "Selamat! Pengajuan {$req->request_no} ({$this->summary($req)}) Anda telah diverifikasi oleh HR ({$hrName}) dan kini berstatus aktif. Absensi pada periode tersebut akan mengikuti aturan WFH/Visit.",
        );

        return $this->mapRow($req->fresh(['employee.user', 'attachments']));
    }

    /**
     * @param  UploadedFile[]  $attachments
     * @return array<string, mixed>
     */
    public function reject(User $user, int $id, ?string $note, array $attachments = []): array
    {
        $req = WfhVisitRequest::findOrFail($id);
        abort_unless(
            in_array($req->status, [ApprovalStatus::PendingSupervisor, ApprovalStatus::PendingHr], true),
            422,
            'This request can no longer be rejected.',
        );

        $isHrStage = $req->status === ApprovalStatus::PendingHr;
        $req->update([
            'status' => ApprovalStatus::Rejected,
            $isHrStage ? 'hr_note' : 'supervisor_note' => $note,
            $isHrStage ? 'hr_decided_at' : 'supervisor_decided_at' => Carbon::now(),
            ...($isHrStage ? ['hr_user_id' => $user->id] : []),
        ]);
        $attachmentStage = $isHrStage ? 'hr' : 'supervisor';
        $this->saveAttachments($req, $attachments, $attachmentStage, $user->id);
        activity()->performedOn($req)->log('WFH/Visit rejected '.$req->request_no);

        $rejectorName = $user->name;
        $stage = $isHrStage ? 'HR' : 'Atasan';
        $reason = $note ? " Catatan: \"{$note}\"." : '';
        $this->notifyRequester(
            $req,
            'Pengajuan WFH/Visit Ditolak',
            "Pengajuan {$req->request_no} ({$this->summary($req)}) Anda ditolak oleh {$stage} ({$rejectorName}).{$reason} Silakan hubungi atasan Anda atau ajukan ulang jika diperlukan.",
            'warning',
        );

        return $this->mapRow($req->fresh(['employee.user', 'attachments']));
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(User $user, int $id): array
    {
        $req = WfhVisitRequest::findOrFail($id);
        abort_unless($req->employee_id === $user->employee?->id, 403, 'You may only cancel your own request.');
        abort_unless(
            in_array($req->status, [ApprovalStatus::PendingSupervisor, ApprovalStatus::PendingHr], true),
            422,
            'This request can no longer be cancelled.',
        );

        $req->update(['status' => ApprovalStatus::Cancelled]);
        activity()->performedOn($req)->log('WFH/Visit cancelled '.$req->request_no);

        return $this->mapRow($req->fresh(['employee.user', 'attachments']));
    }

    /**
     * Returns the fully-approved WFH/Visit active for today, or null.
     * Also returns a pending one (any pending status) for the notice banner.
     *
     * @return array{active: array<string, mixed>|null, pending: array<string, mixed>|null}
     */
    public function todayStatusForEmployee(Employee $employee): array
    {
        $today = Carbon::today()->toDateString();

        $reqs = WfhVisitRequest::query()
            ->where('employee_id', $employee->id)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->whereIn('status', [
                ApprovalStatus::Approved->value,
                ApprovalStatus::PendingSupervisor->value,
                ApprovalStatus::PendingHr->value,
            ])
            ->orderByRaw("CASE status WHEN 'approved' THEN 0 ELSE 1 END")
            ->get();

        $approved = $reqs->first(fn (WfhVisitRequest $r) => $r->status === ApprovalStatus::Approved);
        $pending = $reqs->first(fn (WfhVisitRequest $r) => $r->status !== ApprovalStatus::Approved);

        return [
            'active' => $approved ? [
                'type' => $approved->type,
                'geotag_mode' => $approved->geotag_mode ?? ($approved->type === 'wfh' ? null : 'open'),
                'location_address' => $approved->location_address,
                'location_lat' => $approved->location_lat,
                'location_lng' => $approved->location_lng,
                'location_radius' => $approved->location_radius,
            ] : null,
            'pending' => $pending ? [
                'type' => $pending->type,
                'location_address' => $pending->location_address,
            ] : null,
        ];
    }

    /**
     * @param  UploadedFile[]  $files
     */
    private function saveAttachments(WfhVisitRequest $req, array $files, string $stage, ?int $uploaderId): void
    {
        foreach ($files as $file) {
            $path = $file->store("wfh-visit-attachments/{$req->id}", 'public');
            $req->attachments()->create([
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'stage' => $stage,
                'uploader_id' => $uploaderId,
            ]);
        }
    }

    private function notifyRequester(WfhVisitRequest $req, string $title, string $message, string $type = 'info'): void
    {
        $this->notifier->notify($title, $message, self::REQUESTER_LINK, [$req->employee?->user_id], null, $type);
    }

    /** Human-readable summary: "Work From Home · 25 Jun 2026 (1 hari)" */
    private function summary(WfhVisitRequest $req): string
    {
        $typeLabel = $req->type === 'wfh' ? 'Work From Home' : 'Visit';
        $start = $req->start_date?->format('d M Y') ?? '-';
        $end = $req->end_date?->format('d M Y') ?? '-';
        $days = $req->total_days;

        $dateStr = $start === $end
            ? "{$start} ({$days} hari)"
            : "{$start} – {$end} ({$days} hari)";

        return "{$typeLabel} · {$dateStr}";
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(WfhVisitRequest $r): array
    {
        return [
            'id' => $r->id,
            'request_no' => $r->request_no,
            'employee_id' => $r->employee_id,
            'employee' => $r->employee?->user?->name ?? $r->employee?->employee_no,
            'employee_email' => $r->employee?->user?->email,
            'type' => $r->type,
            'type_label' => $r->type === 'wfh' ? 'Work From Home' : 'Visit',
            'start_date' => $r->start_date?->toDateString(),
            'end_date' => $r->end_date?->toDateString(),
            'total_days' => $r->total_days,
            'reason' => $r->reason,
            'location_address' => $r->location_address,
            'location_lat' => $r->location_lat,
            'location_lng' => $r->location_lng,
            'location_radius' => $r->location_radius,
            'geotag_mode' => $r->geotag_mode,
            'status' => $r->status?->label(),
            'status_value' => $r->status?->value,
            'submitted_at' => $r->created_at?->toIso8601String(),
            'attachments' => $r->attachments->map(fn ($a) => [
                'id' => $a->id,
                'original_name' => $a->original_name,
                'url' => Storage::disk('public')->url($a->path),
                'size' => $a->size,
                'mime_type' => $a->mime_type,
                'stage' => $a->stage,         // submission | supervisor | hr
                'uploader_id' => $a->uploader_id,
            ])->values()->all(),
        ];
    }
}
