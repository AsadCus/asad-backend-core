<?php

namespace App\Rules;

class ManifestRule
{
    public function rules(?int $id = null): array
    {
        return [
            'package_id' => ['required', 'exists:packages,id'],
            'reference_number' => ['required', 'string', 'max:255', 'unique:manifests,reference_number'.($id ? ','.$id : '')],
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
            'status' => ['required', 'string', 'in:draft,confirmed,completed,cancelled'],

            // Travelers
            'travelers' => ['nullable', 'array'],
            'travelers.*.name_as_per_passport' => ['required', 'string', 'max:255'],
            'travelers.*.customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'travelers.*.customer_confirmation_member_id' => ['nullable', 'integer', 'exists:customer_confirmation_members,id'],
            'travelers.*.relationship' => ['nullable', 'string', 'max:100'],
            'travelers.*.passport_no' => ['nullable', 'string', 'max:50'],
            'travelers.*.room_no' => ['nullable', 'string', 'max:50'],
            'travelers.*.room_type' => ['nullable', 'string', 'in:QUAD,TWIN,DOUBLE,TRIPLE,SINGLE'],
            'travelers.*.bed_type' => ['nullable', 'string', 'in:SINGLE,KING,QUEEN'],
            'travelers.*.date_of_birth' => ['nullable', 'date'],
            'travelers.*.age' => ['nullable', 'integer', 'min:0'],
            'travelers.*.meal' => ['nullable', 'string', 'max:255'],
            'travelers.*.remarks' => ['nullable', 'string'],
            'travelers.*.total_cost' => ['nullable', 'numeric', 'min:0'],
            'travelers.*.total_paid' => ['nullable', 'numeric', 'min:0'],
            'travelers.*.outstanding_amount' => ['nullable', 'numeric', 'min:0'],
            'travelers.*.status' => ['nullable', 'string', 'in:assigned,cancelled'],

            // Rooms
            'rooms' => ['nullable', 'array'],
            'rooms.*.location' => ['nullable', 'string', 'max:255'],
            'rooms.*.room_number' => ['nullable', 'string', 'max:50'],
            'rooms.*.room_type' => ['nullable', 'string', 'in:QUAD,TWIN,DOUBLE,TRIPLE,SINGLE'],
            'rooms.*.bed_type' => ['nullable', 'string', 'in:SINGLE,KING,QUEEN'],
            'rooms.*.capacity' => ['nullable', 'integer', 'min:1'],
            'rooms.*.sharing_plan' => ['nullable', 'string', 'in:single,double,triple,quad'],
            'rooms.*.status' => ['nullable', 'string', 'in:pending,filled,finalized'],
            'rooms.*.room_label' => ['nullable', 'string', 'max:255'],
            'rooms.*.members' => ['nullable', 'array'],
            'rooms.*.members.*.manifest_traveler_id' => ['required', 'integer', 'exists:manifest_travelers,id'],
            'rooms.*.members.*.role_in_room' => ['nullable', 'string', 'max:100'],

            // Payments
            'payments' => ['nullable', 'array'],
            'payments.*.manifest_traveler_id' => ['nullable', 'integer', 'exists:manifest_travelers,id'],
            'payments.*.traveler_name' => ['nullable', 'string', 'max:255'],
            'payments.*.description' => ['nullable', 'string', 'max:500'],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.outstanding_amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.payment_date' => ['nullable', 'date'],
            'payments.*.status' => ['nullable', 'string', 'max:50'],

            // Sharing groups
            'sharing_group_ids' => ['nullable', 'array'],
            'sharing_group_ids.*' => ['integer', 'exists:sharing_groups,id'],
        ];
    }

    /**
     * Validation rules for individual room CRUD operations.
     */
    public function roomRules(): array
    {
        return [
            'location' => ['nullable', 'string', 'max:255'],
            'room_number' => ['nullable', 'string', 'max:50'],
            'room_type' => ['nullable', 'string', 'in:QUAD,TWIN,DOUBLE,TRIPLE,SINGLE'],
            'bed_type' => ['nullable', 'string', 'in:SINGLE,KING,QUEEN'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'sharing_plan' => ['nullable', 'string', 'in:single,double,triple,quad'],
            'status' => ['nullable', 'string', 'in:pending,filled,finalized'],
            'room_label' => ['nullable', 'string', 'max:255'],
            'members' => ['nullable', 'array'],
            'members.*.manifest_traveler_id' => ['required', 'integer', 'exists:manifest_travelers,id'],
            'members.*.role_in_room' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Validation rules for individual payment CRUD operations.
     */
    public function paymentRules(): array
    {
        return [
            'manifest_traveler_id' => ['nullable', 'integer', 'exists:manifest_travelers,id'],
            'traveler_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'outstanding_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
        ];
    }
}
