<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'attendance_id',
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
    ];

    protected $casts = [
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'check_in_lat' => 'decimal:8',
        'check_in_lng' => 'decimal:8',
        'check_out_lat' => 'decimal:8',
        'check_out_lng' => 'decimal:8',
    ];

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
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
