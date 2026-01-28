<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralEnquiry extends Model
{
    protected $fillable = [
        'full_name',
        'mobile',
        'email',
        'preferred_destinations',
        'preferred_travelling_date',
        'no_of_adults',
        'no_of_children',
        'requires_mobility_assistance',
    ];

    protected $casts = [
        'preferred_travelling_date' => 'date',
        'no_of_adults' => 'integer',
        'no_of_children' => 'integer',
    ];

    public function getPreferredTravellingDateFormattedAttribute()
    {
        return $this->preferred_travelling_date?->format('d/m/Y');
    }
}
