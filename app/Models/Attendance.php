<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'date',
        'shift_id',
        'check_in_at',
        'check_in_lat',
        'check_in_lng',
        'check_in_photo_path',
        'check_in_location',
        'check_in_branch_id',
        'check_out_at',
        'check_out_lat',
        'check_out_lng',
        'check_out_photo_path',
        'check_out_location',
        'check_out_branch_id',
        'status',
        'late_minutes',
        'early_leave_minutes',
        'work_minutes',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'check_in_lat' => 'decimal:8',
        'check_in_lng' => 'decimal:8',
        'check_out_lat' => 'decimal:8',
        'check_out_lng' => 'decimal:8',
        'status' => AttendanceStatus::class,
        'late_minutes' => 'integer',
        'early_leave_minutes' => 'integer',
        'work_minutes' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function checkInBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'check_in_branch_id');
    }

    public function checkOutBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'check_out_branch_id');
    }
}
