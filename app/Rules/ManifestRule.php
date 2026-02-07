<?php

namespace App\Rules;

class ManifestRule
{
    public function rules(?int $id = null): array
    {
        return [
            'package_id' => ['required', 'exists:packages,id'],
            'reference_number' => ['required', 'string', 'max:255', 'unique:manifests,reference_number' . ($id ? ',' . $id : '')],
            'company_address' => ['nullable', 'string', 'max:500'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'departure_date' => ['required', 'date'],
            'return_date' => ['required', 'date', 'after_or_equal:departure_date'],
            'duration' => ['nullable', 'string', 'max:100'],
            'makkah_hotel' => ['nullable', 'string', 'max:255'],
            'makkah_check_in' => ['nullable', 'date'],
            'makkah_check_out' => ['nullable', 'date'],
            'madinah_hotel' => ['nullable', 'string', 'max:255'],
            'madinah_check_in' => ['nullable', 'date'],
            'madinah_check_out' => ['nullable', 'date'],
            'flight_details' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
            'first_meal' => ['nullable', 'string', 'max:255'],
            'last_meal' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:draft,confirmed,completed'],
            'travelers' => ['nullable', 'array'],
            'travelers.*.name_as_per_passport' => ['required', 'string', 'max:255'],
            'travelers.*.relationship' => ['nullable', 'string', 'max:100'],
            'travelers.*.passport_no' => ['nullable', 'string', 'max:50'],
            'travelers.*.room_no' => ['nullable', 'string', 'max:50'],
            'travelers.*.room_type' => ['nullable', 'string', 'in:QUAD,TWIN,DOUBLE,TRIPLE,SINGLE'],
            'travelers.*.bed_type' => ['nullable', 'string', 'in:SINGLE,KING,QUEEN'],
            'travelers.*.date_of_birth' => ['nullable', 'date'],
            'travelers.*.age' => ['nullable', 'integer', 'min:0'],
            'travelers.*.meal' => ['nullable', 'string', 'max:255'],
            'travelers.*.remarks' => ['nullable', 'string'],
        ];
    }
}
