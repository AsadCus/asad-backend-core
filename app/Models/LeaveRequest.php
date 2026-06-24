<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'request_no',
        'employee_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'days',
        'reason',
        'attachment_path',
        'status',
        'supervisor_id',
        'supervisor_decided_at',
        'supervisor_note',
        'hr_user_id',
        'hr_decided_at',
        'hr_note',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days' => 'decimal:2',
        'status' => ApprovalStatus::class,
        'supervisor_decided_at' => 'datetime',
        'hr_decided_at' => 'datetime',
    ];

    /**
     * Whether the employee has an approved leave covering the given date.
     */
    public static function approvedOnDate(int $employeeId, Carbon|string $date): bool
    {
        $date = ($date instanceof Carbon ? $date : Carbon::parse($date))->toDateString();

        return static::query()
            ->where('employee_id', $employeeId)
            ->where('status', ApprovalStatus::Approved)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    public function hrUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_user_id');
    }
}
