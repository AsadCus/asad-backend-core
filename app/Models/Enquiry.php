<?php

namespace App\Models;

use App\Enums\EnquiryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Enquiry extends Model
{
    protected $fillable = [
        'type',
        'status',
        'name',
        'contact_number',
        'email',
        'created_by',
        'package_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => EnquiryStatus::class,
        ];
    }

    public function generalEnquiry(): HasOne
    {
        return $this->hasOne(GeneralEnquiry::class, 'enquiry_id');
    }

    public function privateEnquiry(): HasOne
    {
        return $this->hasOne(PrivateEnquiry::class, 'enquiry_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customerGroup(): HasOne
    {
        return $this->hasOne(CustomerGroup::class, 'enquiry_id');
    }

    public function remarks(): HasMany
    {
        return $this->hasMany(EnquiryRemark::class, 'enquiry_id')->orderByDesc('created_at');
    }

    public function latestRemark(): HasOne
    {
        return $this->hasOne(EnquiryRemark::class, 'enquiry_id')->latestOfMany();
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    /**
     * Get the child enquiry (general or private).
     */
    public function childEnquiry(): GeneralEnquiry|PrivateEnquiry|null
    {
        if ($this->type === 'general') {
            return $this->generalEnquiry;
        }

        return $this->privateEnquiry;
    }

    /**
     * Get the formatted status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }
}
