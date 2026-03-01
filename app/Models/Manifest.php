<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        return $this->departure_date
            ? Carbon::parse($this->departure_date)->translatedFormat('d F Y')
            : null;
    }

    public function getReturnDateFormattedAttribute(): ?string
    {
        return $this->return_date
            ? Carbon::parse($this->return_date)->translatedFormat('d F Y')
            : null;
    }

    public function getMakkahCheckInFormattedAttribute(): ?string
    {
        return $this->makkah_check_in
            ? Carbon::parse($this->makkah_check_in)->translatedFormat('d F Y')
            : null;
    }

    public function getMakkahCheckOutFormattedAttribute(): ?string
    {
        return $this->makkah_check_out
            ? Carbon::parse($this->makkah_check_out)->translatedFormat('d F Y')
            : null;
    }

    public function getMadinahCheckInFormattedAttribute(): ?string
    {
        return $this->madinah_check_in
            ? Carbon::parse($this->madinah_check_in)->translatedFormat('d F Y')
            : null;
    }

    public function getMadinahCheckOutFormattedAttribute(): ?string
    {
        return $this->madinah_check_out
            ? Carbon::parse($this->madinah_check_out)->translatedFormat('d F Y')
            : null;
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

    public function setMakkahCheckInAttribute(mixed $value): void
    {
        $this->attributes['makkah_check_in'] = ! empty($value)
            ? Carbon::parse($value)->format('Y-m-d')
            : null;
    }

    public function setMakkahCheckOutAttribute(mixed $value): void
    {
        $this->attributes['makkah_check_out'] = ! empty($value)
            ? Carbon::parse($value)->format('Y-m-d')
            : null;
    }

    public function setMadinahCheckInAttribute(mixed $value): void
    {
        $this->attributes['madinah_check_in'] = ! empty($value)
            ? Carbon::parse($value)->format('Y-m-d')
            : null;
    }

    public function setMadinahCheckOutAttribute(mixed $value): void
    {
        $this->attributes['madinah_check_out'] = ! empty($value)
            ? Carbon::parse($value)->format('Y-m-d')
            : null;
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

    public function manifestSharingGroups(): HasMany
    {
        return $this->hasMany(ManifestSharingGroup::class);
    }

    public function accommodationAssignments(): HasMany
    {
        return $this->hasMany(ManifestAccommodationAssignment::class);
    }

    public function sharingGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            SharingGroup::class,
            'manifest_sharing_groups',
            'manifest_id',
            'sharing_group_id',
        )->withPivot('manifest_room_id')
            ->withTimestamps();
    }
}
