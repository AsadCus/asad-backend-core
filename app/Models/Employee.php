<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Observers\EmployeeObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(EmployeeObserver::class)]
class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'employee_no',
        'nik',
        'gender',
        'birth_date',
        'religion_id',
        'education_level_id',
        'hire_date',
        'employment_status',
        'termination_date',
        'branch_id',
        'org_unit_id',
        'work_location_org_unit_id',
        'scope_org_unit_id',
        'supervisor_id',
        'phone',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'is_active',
        'can_check_in',
        'attendance_locked_at',
        'attendance_lock_reason',
        'attendance_lock_dates',
    ];

    protected $casts = [
        'gender' => Gender::class,
        'employment_status' => EmploymentStatus::class,
        'birth_date' => 'date',
        'hire_date' => 'date',
        'termination_date' => 'date',
        'is_active' => 'boolean',
        'can_check_in' => 'boolean',
        'attendance_locked_at' => 'datetime',
        'attendance_lock_dates' => 'array',
    ];

    public function isAttendanceLocked(): bool
    {
        return $this->attendance_locked_at !== null;
    }

    /**
     * Whether this employee's login account should be allowed to authenticate —
     * false once marked inactive or past their termination date.
     */
    public function canLogin(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return ! $this->termination_date || ! $this->termination_date->isPast();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'work_location_org_unit_id');
    }

    public function scopeOrgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'scope_org_unit_id');
    }

    /**
     * Physical site for attendance/geofence: the explicit work location (or its
     * nearest located ancestor), else the nearest located ancestor of the placement —
     * e.g. a branch with no geofence of its own inherits its business unit's location.
     */
    public function resolveWorkLocation(): ?OrgUnit
    {
        if ($this->work_location_org_unit_id) {
            return $this->workLocation?->resolveLocation();
        }

        return $this->orgUnit?->resolveLocation();
    }

    /**
     * Data-scope anchor: the explicit anchor, else the employee's own placement
     * (they see their own org-unit's subtree by default).
     */
    public function resolveScopeOrgUnit(): ?OrgUnit
    {
        if ($this->scope_org_unit_id) {
            return $this->scopeOrgUnit;
        }

        return $this->orgUnit;
    }

    public function religion(): BelongsTo
    {
        return $this->belongsTo(Religion::class);
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'supervisor_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function attendanceCorrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function employeeSchedules(): HasMany
    {
        return $this->hasMany(EmployeeSchedule::class);
    }
}
