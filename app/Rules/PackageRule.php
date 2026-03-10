<?php

namespace App\Rules;

class PackageRule
{
    public function rules(?int $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:open,closed'],

            // Pricing
            'price_single' => ['nullable', 'numeric', 'min:0'],
            'price_double' => ['nullable', 'numeric', 'min:0'],
            'price_triple' => ['nullable', 'numeric', 'min:0'],
            'price_quad' => ['nullable', 'numeric', 'min:0'],
            'child_with_bed_price' => ['nullable', 'numeric', 'min:0'],
            'child_no_bed_price' => ['nullable', 'numeric', 'min:0'],
            'infant_price' => ['nullable', 'numeric', 'min:0'],

            // Dates & Seats
            'departure_date' => ['nullable', 'date'],
            'return_date' => ['nullable', 'date'],
            'total_seats' => ['nullable', 'integer', 'min:0'],
            'seats_left' => ['nullable', 'integer', 'min:0'],

            // Visa
            'visa_type' => ['nullable', 'string', 'max:255'],

            // Vehicle
            'vehicle_type' => ['nullable', 'string', 'max:255'],

            // Train Ticket
            'ticket_type' => ['nullable', 'string', 'max:255'],

            // Package Inclusions
            'included' => ['nullable', 'string'],
            'not_included' => ['nullable', 'string'],
            'offer' => ['nullable', 'string'],

            // Remarks
            'remarks' => ['nullable', 'string'],

            // Accommodations (dynamic)
            'accommodations' => ['nullable', 'array'],
            'accommodations.*.location' => ['required', 'string', 'max:255'],
            'accommodations.*.hotel_name' => ['required', 'string', 'max:255'],
            'accommodations.*.type_of_meal' => ['nullable', 'string', 'max:255'],
            'accommodations.*.check_in' => ['nullable', 'date'],
            'accommodations.*.check_out' => ['nullable', 'date'],

            // Flights (dynamic)
            'flights' => ['nullable', 'array'],
            'flights.*.from' => ['nullable', 'string', 'max:255'],
            'flights.*.to' => ['nullable', 'string', 'max:255'],
            'flights.*.description' => ['nullable', 'string', 'max:255'],
            'flights.*.airline' => ['nullable', 'string', 'max:255'],
            'flights.*.pnr' => ['nullable', 'string', 'max:255'],
            'flights.*.departure_datetime' => ['nullable', 'date'],
            'flights.*.arrival_datetime' => ['nullable', 'date'],

            // Officials (dynamic)
            'officials' => ['nullable', 'array'],
            'officials.*.type' => ['nullable', 'string', 'max:255'],
            'officials.*.name' => ['nullable', 'string', 'max:255'],
            'officials.*.contact_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
