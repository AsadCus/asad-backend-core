<?php

namespace App\Models;

use App\Helpers\NumberGenerator;
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

        // Dates & Seats
        'departure_date',
        'return_date',
        'total_seats',
        'seats_left',

        // Visa
        'visa_type',

        // Vehicle
        'vehicle_type',
        'vehicle_driver_name',
        'vehicle_driver_contact_number',

        // Train Ticket
        'ticket_type',
        'train_description',

        // Package Inclusions
        'included',
        'not_included',
        'offer',

        // Remarks
        'remarks',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'return_date' => 'date',
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

    public function flights(): HasMany
    {
        return $this->hasMany(PackageFlight::class)->orderBy('sort_order');
    }

    public function trainTickets(): HasMany
    {
        return $this->hasMany(PackageTrainTicket::class)->orderBy('sort_order');
    }

    public function transportationPlans(): HasMany
    {
        return $this->hasMany(PackageTransportationPlan::class)->orderBy('sort_order');
    }

    public function rawdahTasreehs(): HasMany
    {
        return $this->hasMany(PackageRawdahTasreeh::class)->orderBy('sort_order');
    }

    public function officials(): HasMany
    {
        return $this->hasMany(PackageOfficial::class)->orderBy('sort_order');
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

    public function getReturnDateFormattedAttribute(): ?string
    {
        return $this->return_date ? Carbon::parse($this->return_date)->translatedFormat('d F Y') : null;
    }

    public function setDepartureDateAttribute(mixed $value): void
    {
        $this->attributes['departure_date'] = ! empty($value)
            ? Carbon::parse($value)->format('Y-m-d')
            : null;
    }

    public function setReturnDateAttribute(mixed $value): void
    {
        $this->attributes['return_date'] = ! empty($value)
            ? Carbon::parse($value)->format('Y-m-d')
            : null;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($package) {
            if (empty($package->package_number)) {
                $package->package_number = NumberGenerator::generate('package');
            }
        });
    }
}
