<?php

namespace App\Models;

use App\Enums\BusinessTripReportStatus;
use App\Enums\BusinessTripStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessTrip extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'btr_no',
        'employee_id',
        'work_type',
        'so_reference',
        'project_name',
        'division',
        'province',
        'city',
        'destination_address',
        'depart_at',
        'return_at',
        'hotel_ref',
        'origin_terminal',
        'dest_terminal',
        'notes',
        'bank',
        'account_no',
        'account_holder',
        'cost_breakdown',
        'grand_total',
        'members',
        'status',
        'leader_id',
        'leader_decided_at',
        'leader_note',
        'hc_user_id',
        'hc_decided_at',
        'hc_note',
        'finance_user_id',
        'finance_decided_at',
        'finance_note',
        'payment_status',
        'paid_at',
        'total_income',
        'actual_cost',
        'total_settlement',
        'variance',
        'report_percentage',
        'report_submitted_at',
        'report_status',
        'report_leader_id',
        'report_leader_decided_at',
        'report_leader_note',
        'report_finance_user_id',
        'report_finance_decided_at',
        'report_finance_note',
        'balance_settled',
    ];

    protected $casts = [
        'depart_at' => 'datetime',
        'return_at' => 'datetime',
        'cost_breakdown' => 'array',
        'members' => 'array',
        'status' => BusinessTripStatus::class,
        'leader_decided_at' => 'datetime',
        'hc_decided_at' => 'datetime',
        'finance_decided_at' => 'datetime',
        'paid_at' => 'datetime',
        'grand_total' => 'integer',
        'total_income' => 'integer',
        'actual_cost' => 'integer',
        'total_settlement' => 'integer',
        'variance' => 'integer',
        'report_percentage' => 'integer',
        'report_submitted_at' => 'datetime',
        'report_status' => BusinessTripReportStatus::class,
        'report_leader_decided_at' => 'datetime',
        'report_finance_decided_at' => 'datetime',
        'balance_settled' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'leader_id');
    }

    public function hcUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hc_user_id');
    }

    public function financeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_user_id');
    }

    public function reportLeader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'report_leader_id');
    }

    public function reportFinanceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'report_finance_user_id');
    }

    public function reportItems(): HasMany
    {
        return $this->hasMany(BusinessTripReportItem::class);
    }
}
