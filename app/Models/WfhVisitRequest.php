<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WfhVisitRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'request_no',
        'employee_id',
        'type',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'location_address',
        'location_lat',
        'location_lng',
        'location_radius',
        'geotag_mode',
        'status',
        'supervisor_id',
        'supervisor_decided_at',
        'supervisor_note',
        'hr_user_id',
        'hr_decided_at',
        'hr_note',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => ApprovalStatus::class,
            'supervisor_decided_at' => 'datetime',
            'hr_decided_at' => 'datetime',
            'location_lat' => 'float',
            'location_lng' => 'float',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    public function hrUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(WfhVisitRequestAttachment::class);
    }
}
