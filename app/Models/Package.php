<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $fillable = [
        'package_number',
        'name',
        'status',

        // Pricing
        'price_single',
        'price_double',
        'price_triple',
        'price_quad',
        'child_with_bed_price',
        'child_no_bed_price',
        'infant_price',

        // Flight Details
        'airline',
        'pnr',
        'departure_date',
        'arrival_date',
        'total_seats',
        'seats_left',

        // Visa
        'visa_type',

        // Vehicle
        'vehicle_type',

        // Train Ticket
        'ticket_type',

        // Package Inclusions
        'included',
        'not_included',
        'offer',

        // Remarks
        'remarks',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'arrival_date' => 'date',
        'price_single' => 'decimal:2',
        'price_double' => 'decimal:2',
        'price_triple' => 'decimal:2',
        'price_quad' => 'decimal:2',
        'child_with_bed_price' => 'decimal:2',
        'child_no_bed_price' => 'decimal:2',
        'infant_price' => 'decimal:2',
        'total_seats' => 'integer',
        'seats_left' => 'integer',
    ];

    public function accommodations(): HasMany
    {
        return $this->hasMany(PackageAccommodation::class);
    }

    public function manifests(): HasMany
    {
        return $this->hasMany(Manifest::class);
    }

    // Formatting Helpers

    public function getLaunchedAttribute(): bool
    {
        return $this->status === 'open';
    }

    public function getDepartureDateFormattedAttribute(): ?string
    {
        return $this->departure_date ? Carbon::parse($this->departure_date)->translatedFormat('d F Y') : null;
    }

    public function getArrivalDateFormattedAttribute(): ?string
    {
        return $this->arrival_date ? Carbon::parse($this->arrival_date)->translatedFormat('d F Y') : null;
    }

    public function setDepartureDateAttribute(mixed $value): void
    {
        $this->attributes['departure_date'] = ! empty($value)
            ? Carbon::parse($value)->format('Y-m-d')
            : null;
    }

    public function setArrivalDateAttribute(mixed $value): void
    {
        $this->attributes['arrival_date'] = ! empty($value)
            ? Carbon::parse($value)->format('Y-m-d')
            : null;
    }
}
