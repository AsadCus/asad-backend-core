<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\AttendanceCorrectionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceCorrection extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'correction_no',
        'employee_id',
        'attendance_id',
        'date',
        'correction_type',
        'requested_check_in',
        'requested_check_out',
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
        'date' => 'date',
        'correction_type' => AttendanceCorrectionType::class,
        'status' => ApprovalStatus::class,
        'requested_check_in' => 'datetime',
        'requested_check_out' => 'datetime',
        'supervisor_decided_at' => 'datetime',
        'hr_decided_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
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
