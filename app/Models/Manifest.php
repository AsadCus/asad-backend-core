<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manifest extends Model
{
    protected $fillable = [
        'package_id',
        'reference_number',
        'company_address',
        'company_phone',
        'departure_date',
        'return_date',
        'duration',
        'makkah_hotel',
        'makkah_check_in',
        'makkah_check_out',
        'madinah_hotel',
        'madinah_check_in',
        'madinah_check_out',
        'flight_details',
        'notes',
        'first_meal',
        'last_meal',
        'status',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'return_date' => 'date',
        'makkah_check_in' => 'date',
        'makkah_check_out' => 'date',
        'madinah_check_in' => 'date',
        'madinah_check_out' => 'date',
        'flight_details' => 'array',
    ];

    public function getDepartureDateFormattedAttribute(): ?string
    {
        return $this->departure_date?->format('d/m/Y');
    }

    public function getReturnDateFormattedAttribute(): ?string
    {
        return $this->return_date?->format('d/m/Y');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function travelers(): HasMany
    {
        return $this->hasMany(ManifestTraveler::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(ManifestRoom::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ManifestPayment::class);
    }
}
