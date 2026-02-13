<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class PrivateEnquiry extends Model
{
    protected $fillable = [
        'full_name',
        'contact_number',
        'email',
        'passport_expiry_date',
        'departure_date',
        'return_date',
        'no_of_pax',
        'no_of_children',
        'airline',
        'class',
        'require_mutawif',
        'require_umrah_course',
        'require_umrah_official',
        'makkah_or_madinah_first',
        'no_of_nights_makkah',
        'hotel_makkah',
        'meals_makkah',
        'no_of_nights_madinah',
        'hotel_madinah',
        'meals_madinah',
        'land_transfer',
        'add_on_speed_train',
        'require_meet_greet',
        'require_mutawiffah_ustazah_rawdah',
        'madinah_tour_with_mutawif',
        'makkah_tour_with_mutawif',
        'has_chronic_disease',
        'chronic_disease_details',
        'need_wheelchair',
        'other_remarks',
    ];

    protected $casts = [
        'passport_expiry_date' => 'date',
        'departure_date' => 'date',
        'return_date' => 'date',
        'require_mutawif' => 'boolean',
        'require_umrah_course' => 'boolean',
        'require_umrah_official' => 'boolean',
        'add_on_speed_train' => 'boolean',
        'require_meet_greet' => 'boolean',
        'require_mutawiffah_ustazah_rawdah' => 'boolean',
        'madinah_tour_with_mutawif' => 'boolean',
        'makkah_tour_with_mutawif' => 'boolean',
        'has_chronic_disease' => 'boolean',
    ];

    // Formatting Helpers
    public function getPassportExpiryDateFormattedAttribute(): ?string
    {
        return $this->passport_expiry_date
            ? Carbon::parse($this->passport_expiry_date)->translatedFormat('d F Y')
            : null;
    }

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
}
