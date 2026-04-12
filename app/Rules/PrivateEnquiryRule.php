<?php

namespace App\Rules;

class PrivateEnquiryRule
{
    public function rules(?int $id = null): array
    {
        return [
            'enquiry_number' => ['nullable', 'string', 'max:100'],
            'number_format_id' => ['nullable', 'integer', 'exists:numbering_formats,id'],
            'name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'passport_expiry_date' => ['required', 'date'],
            'departure_date' => ['required', 'date'],
            'return_date' => ['required', 'date'],
            'no_of_pax' => ['required', 'integer', 'min:1'],
            'no_of_children' => ['required', 'integer', 'min:0'],
            'airline' => ['required'],
            'class' => ['required'],
            'require_mutawif' => ['required', 'boolean'],
            'require_umrah_course' => ['required', 'boolean'],
            'require_umrah_official' => ['required', 'boolean'],
            'makkah_or_madinah_first' => ['required'],
            'no_of_nights_makkah' => ['required'],
            'hotel_makkah' => ['required'],
            'meals_makkah' => ['required'],
            'no_of_nights_madinah' => ['required'],
            'hotel_madinah' => ['required'],
            'meals_madinah' => ['required', 'string', 'max:50'],
            'land_transfer' => ['required'],
            'add_on_speed_train' => ['required', 'boolean'],
            'require_meet_greet' => ['required', 'boolean'],
            'require_mutawiffah_ustazah_rawdah' => ['required', 'boolean'],
            'madinah_tour_with_mutawif' => ['required', 'boolean'],
            'makkah_tour_with_mutawif' => ['required', 'boolean'],
            'has_chronic_disease' => ['required', 'boolean'],
            'chronic_disease_details' => ['nullable', 'string'],
            'need_wheelchair' => ['required'],
            'other_remarks' => ['nullable', 'string'],
        ];
    }
}
