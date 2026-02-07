<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageAccommodation extends Model
{
    protected $fillable = [
        'package_id',
        'location',
        'hotel_name',
        'type_of_meal',
        'check_in',
        'check_out',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
    ];

    public function getCheckInFormattedAttribute(): ?string
    {
        return $this->check_in?->format('d/m/Y');
    }

    public function getCheckOutFormattedAttribute(): ?string
    {
        return $this->check_out?->format('d/m/Y');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
