<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageAccommodation extends Model
{
    protected $fillable = [
        'package_id',
        'location',
        'hotel_name',
        'ic',
        'ic_contact_number',
        'remarks',
        'type_of_meal',
        'first_meal',
        'last_meal',
        'check_in',
        'check_out',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
    ];

    public function getCheckInFormattedAttribute(): ?string
    {
        return $this->check_in ? Carbon::parse($this->check_in)->translatedFormat('d F Y') : null;
    }

    public function getCheckOutFormattedAttribute(): ?string
    {
        return $this->check_out ? Carbon::parse($this->check_out)->translatedFormat('d F Y') : null;
    }

    public function setCheckInAttribute(mixed $value): void
    {
        $this->attributes['check_in'] = ! empty($value)
            ? Carbon::parse($value)->format('Y-m-d')
            : null;
    }

    public function setCheckOutAttribute(mixed $value): void
    {
        $this->attributes['check_out'] = ! empty($value)
            ? Carbon::parse($value)->format('Y-m-d')
            : null;
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
