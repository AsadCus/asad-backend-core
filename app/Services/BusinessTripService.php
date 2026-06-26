<?php

namespace App\Services;

use App\Enums\BusinessTripReportStatus;
use App\Enums\BusinessTripStatus;
use App\Enums\PaymentStatus;
use App\Enums\WorkType;
use App\Models\BusinessTrip;
use App\Models\BusinessTripReportItem;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BusinessTripService
{
    /** Where approvers act on a trip / its report. */
    private const APPROVER_LINK = '/business-trip/admin';

    /** Where the requester tracks their own requests. */
    private const REQUESTER_LINK = '/requests';

    public function __construct(private HrisNotifier $notifier) {}

    /** Notify the trip's requester (their own user). */
    private function notifyRequester(BusinessTrip $trip, string $title, string $message): void
    {
        $this->notifier->notify($title, $message, self::REQUESTER_LINK, [$trip->employee?->user_id]);
    }

    /** Notify a specific employee's user (e.g. the assigned leader), if there is one. */
    private function notifyEmployee(?int $employeeId, string $title, string $message): void
    {
        $userId = $employeeId ? Employee::query()->whereKey($employeeId)->value('user_id') : null;
        $this->notifier->notify($title, $message, self::APPROVER_LINK, [$userId]);
    }

    /**
     * Trip ids the user may read. Null = all (view-all).
     *
     * @return array<int>|null
     */
    private function accessibleEmployeeIds(User $user): ?array
    {
        if ($user->can('hris.business-trip view-all')) {
            return null;
        }

        if ($user->can('hris.business-trip view-team')) {
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
     * The leader stage is per-employee: only the trip's assigned leader may act on it. An
     * administrator overrides every stage so a trip never gets stuck if the leader is unavailable.
     */
    private function assertLeader(User $user, ?int $leaderId): void
    {
        if ($user->hasRole('administrator')) {
            return;
        }

        abort_unless($leaderId !== null && $user->employee?->id === $leaderId, 403, 'Only the assigned leader may act on this stage.');
    }

    /**
     * Stage authority for the central (HC / finance) stages — administrator always passes.
     */
    private function assertCan(User $user, string $permission): void
    {
        abort_unless($user->hasRole('administrator') || $user->can($permission), 403, 'You may not act on this stage.');
    }

    /**
     * @param  array{status?:string,city?:string,q?:string}  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getForDataTable(User $user, array $filters = []): array
    {
        $query = BusinessTrip::query()->with('employee.user')->latest('depart_at');

        $ids = $this->accessibleEmployeeIds($user);
        if ($ids !== null) {
            $query->whereIn('employee_id', $ids);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['city'])) {
            $query->where('city', $filters['city']);
        }
        if (! empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function ($w) use ($q) {
                $w->where('btr_no', 'like', "%{$q}%")
                    ->orWhereHas('employee.user', fn ($u) => $u->where('name', 'like', "%{$q}%"));
            });
        }

        return $query->get()->map(fn (BusinessTrip $t) => $this->mapRow($t))->all();
    }

    /**
     * The authenticated user's own business trips, regardless of role.
     *
     * @param  array{status?:string}  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getMyList(User $user, array $filters = []): array
    {
        $query = BusinessTrip::query()
            ->with('employee.user')
            ->where('employee_id', $user->employee?->id ?? 0)
            ->latest('depart_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get()->map(fn (BusinessTrip $t) => $this->mapRow($t))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetail(User $user, int $id): array
    {
        $trip = BusinessTrip::query()
            ->with(['employee.user', 'leader.user', 'hcUser', 'financeUser'])
            ->findOrFail($id);

        $ids = $this->accessibleEmployeeIds($user);
        if ($ids !== null && ! in_array($trip->employee_id, $ids, true)) {
            abort(403, 'You may not view this business trip.');
        }

        return $this->mapRow($trip) + [
            'work_type' => $trip->work_type,
            'so_reference' => $trip->so_reference,
            'division' => $trip->division,
            'province' => $trip->province,
            'destination_address' => $trip->destination_address,
            'hotel_ref' => $trip->hotel_ref,
            'origin_terminal' => $trip->origin_terminal,
            'dest_terminal' => $trip->dest_terminal,
            'notes' => $trip->notes,
            'bank' => $trip->bank,
            'account_no' => $trip->account_no,
            'account_holder' => $trip->account_holder,
            'cost_breakdown' => $trip->cost_breakdown,
            'members' => $trip->members ?? [],
            'leader' => $trip->leader?->user?->name,
            'leader_note' => $trip->leader_note,
            'hc' => $trip->hcUser?->name,
            'hc_note' => $trip->hc_note,
            'finance' => $trip->financeUser?->name,
            'finance_note' => $trip->finance_note,
        ];
    }

    /**
     * Employee submits a BTR → status pending_leader.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function store(User $user, array $data): array
    {
        $employee = $user->employee;
        abort_if(! $employee, 422, 'No employee profile is linked to your account.');

        return DB::transaction(function () use ($employee, $data) {
            $grandTotal = $this->sumCostBreakdown($data['cost_breakdown']);

            // No supervisor → skip the (empty) leader stage and start at HC.
            $leaderId = $employee->supervisor_id;

            $trip = BusinessTrip::create([
                'btr_no' => 'BTF/HCD/'.Carbon::now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                'employee_id' => $employee->id,
                'work_type' => $data['work_type'],
                'so_reference' => $data['so_reference'] ?? null,
                'project_name' => $data['project_name'],
                'division' => $data['division'] ?? null,
                'province' => $data['province'],
                'city' => $data['city'],
                'destination_address' => $data['destination_address'],
                'depart_at' => $data['depart_at'],
                'return_at' => $data['return_at'],
                'hotel_ref' => $data['hotel_ref'] ?? null,
                'origin_terminal' => $data['origin_terminal'] ?? null,
                'dest_terminal' => $data['dest_terminal'] ?? null,
                'notes' => $data['notes'] ?? null,
                'bank' => $data['bank'],
                'account_no' => $data['account_no'],
                'account_holder' => $data['account_holder'],
                'cost_breakdown' => $data['cost_breakdown'],
                'grand_total' => $grandTotal,
                'members' => $data['members'] ?? [],
                'status' => $leaderId ? BusinessTripStatus::PendingLeader : BusinessTripStatus::PendingHc,
                'leader_id' => $leaderId,
            ]);

            activity()->performedOn($trip)->log('Business trip submitted '.$trip->btr_no);

            if ($leaderId) {
                $this->notifyEmployee($leaderId, 'Business trip awaiting your approval', $trip->btr_no.' needs your leader approval.');
            } else {
                $this->notifier->notify('Business trip awaiting HC approval', $trip->btr_no.' needs HC approval.', self::APPROVER_LINK, [], 'hr');
            }

            return $this->mapRow($trip->fresh('employee.user'));
        });
    }

    /**
     * @return int Rupiah sum of cost×qty across every item in every section.
     */
    private function sumCostBreakdown(array $sections): int
    {
        $sum = 0;
        foreach ($sections as $section) {
            foreach ($section['items'] ?? [] as $item) {
                $sum += (int) round(($item['cost'] ?? 0) * ($item['qty'] ?? 0));
            }
        }

        return $sum;
    }

    /**
     * @return array<string, mixed>
     */
    public function approveLeader(User $user, int $id, ?string $note): array
    {
        return DB::transaction(function () use ($user, $id, $note) {
            $trip = BusinessTrip::lockForUpdate()->findOrFail($id);
            abort_unless($trip->status === BusinessTripStatus::PendingLeader, 422, 'This trip is not awaiting leader approval.');
            $this->assertLeader($user, $trip->leader_id);

            $trip->update([
                'status' => BusinessTripStatus::PendingHc,
                'leader_decided_at' => Carbon::now(),
                'leader_note' => $note,
            ]);
            activity()->performedOn($trip)->log('Business trip approved by leader '.$trip->btr_no);
            $this->notifier->notify('Business trip awaiting HC approval', $trip->btr_no.' was approved by the leader.', self::APPROVER_LINK, [], 'hr');
            $this->notifyRequester($trip, 'Business trip approved by leader', $trip->btr_no.' moved to HC review.');

            return $this->mapRow($trip->fresh('employee.user'));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function approveHc(User $user, int $id, ?string $note): array
    {
        return DB::transaction(function () use ($user, $id, $note) {
            $trip = BusinessTrip::lockForUpdate()->findOrFail($id);
            abort_unless($trip->status === BusinessTripStatus::PendingHc, 422, 'This trip is not awaiting HC approval.');

            $trip->update([
                'status' => BusinessTripStatus::PendingFinance,
                'hc_user_id' => $user->id,
                'hc_decided_at' => Carbon::now(),
                'hc_note' => $note,
            ]);
            activity()->performedOn($trip)->log('Business trip approved by HC '.$trip->btr_no);
            $this->notifier->notify('Business trip awaiting finance approval', $trip->btr_no.' was approved by HC.', self::APPROVER_LINK, [], 'administrator');
            $this->notifyRequester($trip, 'Business trip approved by HC', $trip->btr_no.' moved to finance review.');

            return $this->mapRow($trip->fresh('employee.user'));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function approveFinance(User $user, int $id, ?string $note): array
    {
        return DB::transaction(function () use ($user, $id, $note) {
            $trip = BusinessTrip::lockForUpdate()->findOrFail($id);
            abort_unless($trip->status === BusinessTripStatus::PendingFinance, 422, 'This trip is not awaiting finance approval.');

            $trip->update([
                'status' => BusinessTripStatus::Approved,
                'finance_user_id' => $user->id,
                'finance_decided_at' => Carbon::now(),
                'finance_note' => $note,
            ]);
            activity()->performedOn($trip)->log('Business trip approved by finance '.$trip->btr_no);
            $this->notifyRequester($trip, 'Business trip approved', $trip->btr_no.' is fully approved and pending disbursement.');

            return $this->mapRow($trip->fresh('employee.user'));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function reject(User $user, int $id, ?string $note): array
    {
        return DB::transaction(function () use ($user, $id, $note) {
            $trip = BusinessTrip::lockForUpdate()->findOrFail($id);
            $pending = [BusinessTripStatus::PendingLeader, BusinessTripStatus::PendingHc, BusinessTripStatus::PendingFinance];
            abort_unless(in_array($trip->status, $pending, true), 422, 'This trip can no longer be rejected.');

            // Strict per-stage: only the current stage's approver may reject.
            match ($trip->status) {
                BusinessTripStatus::PendingLeader => $this->assertLeader($user, $trip->leader_id),
                BusinessTripStatus::PendingHc => $this->assertCan($user, 'hris.business-trip approve-hc'),
                BusinessTripStatus::PendingFinance => $this->assertCan($user, 'hris.business-trip approve-finance'),
                default => null,
            };

            $stageField = match ($trip->status) {
                BusinessTripStatus::PendingLeader => 'leader_note',
                BusinessTripStatus::PendingHc => 'hc_note',
                BusinessTripStatus::PendingFinance => 'finance_note',
                default => 'leader_note',
            };

            $trip->update([
                'status' => BusinessTripStatus::Rejected,
                $stageField => $note,
                ...($trip->status === BusinessTripStatus::PendingHc ? ['hc_user_id' => $user->id] : []),
                ...($trip->status === BusinessTripStatus::PendingFinance ? ['finance_user_id' => $user->id] : []),
            ]);
            activity()->performedOn($trip)->log('Business trip rejected '.$trip->btr_no);
            $this->notifyRequester($trip, 'Business trip rejected', $trip->btr_no.' was rejected.');

            return $this->mapRow($trip->fresh('employee.user'));
        });
    }

    /**
     * Requester cancels while still pending.
     *
     * @return array<string, mixed>
     */
    public function cancel(User $user, int $id): array
    {
        return DB::transaction(function () use ($user, $id) {
            $trip = BusinessTrip::lockForUpdate()->findOrFail($id);
            abort_unless($trip->employee_id === $user->employee?->id, 403, 'You may only cancel your own business trip.');
            $pending = [BusinessTripStatus::PendingLeader, BusinessTripStatus::PendingHc, BusinessTripStatus::PendingFinance];
            abort_unless(in_array($trip->status, $pending, true), 422, 'This trip can no longer be cancelled.');

            $trip->update(['status' => BusinessTripStatus::Cancelled]);
            activity()->performedOn($trip)->log('Business trip cancelled '.$trip->btr_no);

            return $this->mapRow($trip->fresh('employee.user'));
        });
    }

    /**
     * Finance disburses the approved grand total.
     *
     * @return array<string, mixed>
     */
    public function markPaid(User $user, int $id): array
    {
        return DB::transaction(function () use ($id) {
            $trip = BusinessTrip::lockForUpdate()->findOrFail($id);
            abort_unless($trip->status === BusinessTripStatus::Approved, 422, 'This trip is not approved yet.');
            abort_unless($trip->payment_status === PaymentStatus::Unpaid, 422, 'This trip has already been paid.');

            $trip->update(['payment_status' => PaymentStatus::Paid, 'paid_at' => Carbon::now()]);
            activity()->performedOn($trip)->log('Business trip disbursed '.$trip->btr_no);
            $this->notifyRequester($trip, 'Business trip disbursed', $trip->btr_no.' has been paid out — submit your report after the trip.');

            return $this->mapRow($trip->fresh('employee.user'));
        });
    }

    /**
     * Employee submits the post-trip report ("Laporan"): a multi-ledger reconciliation
     * (income / expense / settlement / ticket line items) against the disbursed budget.
     * Replaces any previously submitted items wholesale — e.g. when resubmitting after a
     * rejection — and restarts its own Leader → Finance approval cycle.
     *
     * @param  array<int, array{category:string,date:string,description:string,kategori?:string|null,amount:numeric-string|int|float,attachment?:UploadedFile|null}>  $items
     * @return array<string, mixed>
     */
    public function submitReport(User $user, int $id, array $items): array
    {
        $trip = BusinessTrip::findOrFail($id);
        abort_unless($trip->employee_id === $user->employee?->id, 403, 'You may only report your own business trip.');
        abort_unless($trip->payment_status === PaymentStatus::Paid, 422, 'This trip has not been disbursed yet.');

        return DB::transaction(function () use ($trip, $items) {
            // Items are replaced wholesale; remember the old receipt files so we can prune the
            // ones no longer referenced after the rebuild (avoids orphaned uploads in storage).
            $oldPaths = $trip->reportItems()->pluck('attachment_path')->filter()->values()->all();
            $trip->reportItems()->delete();

            $totals = ['income' => 0, 'expense' => 0, 'settlement' => 0, 'ticket' => 0];
            $receiptBacked = 0;
            $receiptable = 0; // expense + ticket — the categories a receipt actually proves.
            $keptPaths = [];

            foreach ($items as $item) {
                $amount = (int) round((float) $item['amount']);
                $totals[$item['category']] = ($totals[$item['category']] ?? 0) + $amount;

                /** @var UploadedFile|null $attachment */
                $attachment = $item['attachment'] ?? null;
                $attachmentPath = $attachment
                    ? $attachment->store("business-trips/{$trip->employee_id}", 'public')
                    : ($item['attachment_path'] ?? null);
                if ($attachmentPath) {
                    $keptPaths[] = $attachmentPath;
                }

                if (in_array($item['category'], ['expense', 'ticket'], true)) {
                    $receiptable += $amount;
                    if ($attachmentPath) {
                        $receiptBacked += $amount;
                    }
                }

                BusinessTripReportItem::create([
                    'business_trip_id' => $trip->id,
                    'category' => $item['category'],
                    'date' => $item['date'],
                    'description' => $item['description'],
                    'kategori' => $item['kategori'] ?? null,
                    'amount' => $amount,
                    'attachment_path' => $attachmentPath,
                ]);
            }

            // Prune receipt files no longer referenced after the wholesale replace.
            $orphans = array_diff($oldPaths, $keptPaths);
            if ($orphans !== []) {
                Storage::disk('public')->delete($orphans);
            }

            $actualCost = $totals['expense'] + $totals['ticket'];
            $variance = $totals['income'] - $actualCost - $totals['settlement'];
            $percentage = $receiptable > 0 ? (int) round($receiptBacked / $receiptable * 100) : 100;

            $trip->update([
                'total_income' => $totals['income'],
                'actual_cost' => $actualCost,
                'total_settlement' => $totals['settlement'],
                'variance' => $variance,
                'report_percentage' => $percentage,
                'report_submitted_at' => Carbon::now(),
                'report_status' => $trip->leader_id ? BusinessTripReportStatus::PendingLeader : BusinessTripReportStatus::PendingFinance,
                'report_leader_id' => $trip->leader_id,
                'report_leader_decided_at' => null,
                'report_leader_note' => null,
                'report_finance_user_id' => null,
                'report_finance_decided_at' => null,
                'report_finance_note' => null,
                'balance_settled' => false,
            ]);
            activity()->performedOn($trip)->log('Business trip report submitted '.$trip->btr_no);

            if ($trip->leader_id) {
                $this->notifyEmployee($trip->leader_id, 'Trip report awaiting your approval', $trip->btr_no.' report needs your approval.');
            } else {
                $this->notifier->notify('Trip report awaiting finance approval', $trip->btr_no.' report needs finance approval.', self::APPROVER_LINK, [], 'administrator');
            }

            return $this->mapRow($trip->fresh('employee.user'));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function getReportDetail(User $user, int $id): array
    {
        $trip = BusinessTrip::query()
            ->with(['employee.user', 'reportLeader.user', 'reportFinanceUser', 'reportItems'])
            ->findOrFail($id);

        $ids = $this->accessibleEmployeeIds($user);
        if ($ids !== null && ! in_array($trip->employee_id, $ids, true)) {
            abort(403, 'You may not view this business trip report.');
        }

        return $this->mapRow($trip) + [
            'items' => $trip->reportItems->map(fn (BusinessTripReportItem $i) => [
                'id' => $i->id,
                'category' => $i->category,
                'date' => $i->date->format('Y-m-d'),
                'description' => $i->description,
                'kategori' => $i->kategori,
                'amount' => $i->amount,
                'attachment_path' => $i->attachment_path,
                'attachment_url' => $i->attachment_path ? Storage::disk('public')->url($i->attachment_path) : null,
            ])->all(),
            'report_leader' => $trip->reportLeader?->user?->name,
            'report_leader_note' => $trip->report_leader_note,
            'report_finance' => $trip->reportFinanceUser?->name,
            'report_finance_note' => $trip->report_finance_note,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function approveReportLeader(User $user, int $id, ?string $note): array
    {
        return DB::transaction(function () use ($user, $id, $note) {
            $trip = BusinessTrip::lockForUpdate()->findOrFail($id);
            abort_unless($trip->report_status === BusinessTripReportStatus::PendingLeader, 422, 'This report is not awaiting leader approval.');
            $this->assertLeader($user, $trip->report_leader_id);

            $trip->update([
                'report_status' => BusinessTripReportStatus::PendingFinance,
                'report_leader_decided_at' => Carbon::now(),
                'report_leader_note' => $note,
            ]);
            activity()->performedOn($trip)->log('Business trip report approved by leader '.$trip->btr_no);
            $this->notifier->notify('Trip report awaiting finance approval', $trip->btr_no.' report approved by leader.', self::APPROVER_LINK, [], 'administrator');
            $this->notifyRequester($trip, 'Trip report approved by leader', $trip->btr_no.' report moved to finance.');

            return $this->mapRow($trip->fresh('employee.user'));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function approveReportFinance(User $user, int $id, ?string $note): array
    {
        return DB::transaction(function () use ($user, $id, $note) {
            $trip = BusinessTrip::lockForUpdate()->findOrFail($id);
            abort_unless($trip->report_status === BusinessTripReportStatus::PendingFinance, 422, 'This report is not awaiting finance approval.');

            $trip->update([
                'report_status' => BusinessTripReportStatus::Approved,
                'report_finance_user_id' => $user->id,
                'report_finance_decided_at' => Carbon::now(),
                'report_finance_note' => $note,
            ]);
            activity()->performedOn($trip)->log('Business trip report approved by finance '.$trip->btr_no);
            $this->notifyRequester($trip, 'Trip report approved', $trip->btr_no.' report is approved.');

            return $this->mapRow($trip->fresh('employee.user'));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function rejectReport(User $user, int $id, ?string $note): array
    {
        return DB::transaction(function () use ($user, $id, $note) {
            $trip = BusinessTrip::lockForUpdate()->findOrFail($id);
            $pending = [BusinessTripReportStatus::PendingLeader, BusinessTripReportStatus::PendingFinance];
            abort_unless(in_array($trip->report_status, $pending, true), 422, 'This report can no longer be rejected.');

            // Strict per-stage: only the current stage's approver may reject.
            match ($trip->report_status) {
                BusinessTripReportStatus::PendingLeader => $this->assertLeader($user, $trip->report_leader_id),
                BusinessTripReportStatus::PendingFinance => $this->assertCan($user, 'hris.business-trip approve-finance'),
                default => null,
            };

            $stageField = $trip->report_status === BusinessTripReportStatus::PendingFinance
                ? 'report_finance_note' : 'report_leader_note';

            $trip->update([
                'report_status' => BusinessTripReportStatus::Rejected,
                $stageField => $note,
                ...($trip->report_status === BusinessTripReportStatus::PendingFinance ? ['report_finance_user_id' => $user->id] : []),
            ]);
            activity()->performedOn($trip)->log('Business trip report rejected '.$trip->btr_no);
            $this->notifyRequester($trip, 'Trip report rejected', $trip->btr_no.' report was rejected — please revise and resubmit.');

            return $this->mapRow($trip->fresh('employee.user'));
        });
    }

    /**
     * Finance confirms the leftover variance has been physically returned/reimbursed —
     * only meaningful once the report itself has cleared its own approval.
     *
     * @return array<string, mixed>
     */
    public function settleBalance(User $user, int $id): array
    {
        return DB::transaction(function () use ($id) {
            $trip = BusinessTrip::lockForUpdate()->findOrFail($id);
            abort_unless($trip->report_status === BusinessTripReportStatus::Approved, 422, 'This trip report has not been approved yet.');

            $trip->update(['balance_settled' => true]);
            activity()->performedOn($trip)->log('Business trip balance settled '.$trip->btr_no);
            $this->notifyRequester($trip, 'Trip balance settled', $trip->btr_no.' balance has been settled — this trip is complete.');

            return $this->mapRow($trip->fresh('employee.user'));
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(BusinessTrip $t): array
    {
        return [
            'id' => $t->id,
            'btr_no' => $t->btr_no,
            'employee_id' => $t->employee_id,
            'employee' => $t->employee?->user?->name ?? $t->employee?->employee_no,
            'employee_email' => $t->employee?->user?->email,
            'so_or_operational' => $t->so_reference ?: ($t->work_type === WorkType::Operational ? 'Operational' : null),
            'city' => $t->city,
            'depart_at' => $t->depart_at?->format('Y-m-d'),
            'return_at' => $t->return_at?->format('Y-m-d'),
            'grand_total' => $t->grand_total,
            'total_income' => $t->total_income,
            'actual_cost' => $t->actual_cost,
            'total_settlement' => $t->total_settlement,
            'variance' => $t->variance,
            'status' => $t->status?->label(),
            'status_value' => $t->status?->value,
            'leader_approved' => $t->leader_decided_at !== null,
            'hc_approved' => $t->hc_decided_at !== null,
            'finance_approved' => $t->finance_decided_at !== null,
            'payment_status' => $t->payment_status,
            'report_submitted' => $t->report_submitted_at !== null,
            'report_percentage' => $t->report_percentage,
            'report_status' => $t->report_status?->label(),
            'report_status_value' => $t->report_status?->value,
            'report_leader_approved' => $t->report_leader_decided_at !== null,
            'report_finance_approved' => $t->report_finance_decided_at !== null,
            'balance_settled' => $t->balance_settled,
            'created_at' => $t->created_at?->toIso8601String(),
        ];
    }
}
