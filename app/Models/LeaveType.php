<?php

namespace App\Models;

use App\Enums\Gender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'max_days_per_year',
        'requires_balance',
        'requires_attachment',
        'is_paid',
        'gender_restriction',
        'description',
        'is_active',
    ];

    protected $casts = [
        'max_days_per_year' => 'decimal:2',
        'requires_balance' => 'boolean',
        'requires_attachment' => 'boolean',
        'is_paid' => 'boolean',
        'gender_restriction' => Gender::class,
        'is_active' => 'boolean',
    ];

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
