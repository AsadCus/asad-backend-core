<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerGroup extends Model
{
    protected $fillable = [
        'enquiry_id',
        'created_by',
        'package_id',
        'package_room_type',
        'package_category',
        'date_of_application',
    ];

    protected function casts(): array
    {
        return [
            'date_of_application' => 'date',
        ];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class, 'enquiry_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(CustomerGroupMember::class, 'customer_group_id');
    }

    public function leader(): ?CustomerGroupMember
    {
        return $this->members()->where('is_leader', true)->first();
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    // Formatting Helpers
    public function getDateOfApplicationFormattedAttribute(): ?string
    {
        return $this->date_of_application
            ? Carbon::parse($this->date_of_application)->translatedFormat('d F Y')
            : null;
    }
}
