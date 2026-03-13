<?php

namespace App\Rules;

class ManifestRule
{
    public function rules(?int $id = null): array
    {
        return [
            'package_id' => ['required', 'exists:packages,id'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:draft,confirmed,completed,cancelled'],

            // Travelers
            'travelers' => ['nullable', 'array'],
            'travelers.*.name_as_per_passport' => ['nullable', 'string', 'max:255'],
            'travelers.*.customer_confirmation_member_id' => ['nullable', 'integer', 'exists:customer_confirmation_members,id'],
            'travelers.*.customer_confirmation_id' => ['nullable', 'integer', 'exists:customer_confirmations,id'],
            'travelers.*.relationship' => ['nullable', 'string', 'max:100'],
            'travelers.*.passport_number' => ['nullable', 'string', 'max:50'],
            'travelers.*.date_of_birth' => ['nullable', 'date'],
            'travelers.*.remarks' => ['nullable', 'string'],
            'travelers.*.status' => ['nullable', 'string', 'in:draft,pending_payment,partially_paid,confirmed,unavailable,assigned,cancelled'],
            'travelers.*.sharing_group_key' => ['nullable', 'string', 'max:255'],
            'travelers.*.manifest_sharing_group_id' => ['nullable', 'integer', 'exists:manifest_sharing_groups,id'],
            'travelers.*.sharing_group_id' => ['nullable', 'integer', 'exists:manifest_sharing_groups,id'],

            // Rooms
            'rooms' => ['nullable', 'array'],
            'rooms.*.location' => ['nullable', 'string', 'max:255'],
            'rooms.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'rooms.*.relationship' => ['nullable', 'string', 'max:100'],
            'rooms.*.room_number' => ['nullable', 'string', 'max:50'],
            'rooms.*.room_type' => ['nullable', 'string', 'in:single,twin,double,triple,quad'],
            'rooms.*.bed_type' => ['nullable', 'string', 'in:single,king,queen'],
            'rooms.*.capacity' => ['nullable', 'integer', 'min:1'],
            'rooms.*.sharing_plan' => ['nullable', 'string', 'in:single,double,triple,quad'],
            'rooms.*.status' => ['nullable', 'string', 'in:pending,filled,finalized'],
            'rooms.*.room_label' => ['nullable', 'string', 'max:255'],
            'rooms.*.meal' => ['nullable', 'string', 'max:255'],
            'rooms.*.remarks' => ['nullable', 'string'],
            'rooms.*.members' => ['nullable', 'array'],
            'rooms.*.members.*.manifest_traveler_id' => ['nullable', 'integer', 'exists:manifest_members,id'],
            'rooms.*.members.*.customer_confirmation_member_id' => ['nullable', 'integer', 'exists:customer_confirmation_members,id'],
            'rooms.*.members.*.sharing_plan' => ['nullable', 'string', 'in:single,double,triple,quad'],
            'rooms.*.members.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'rooms.*.members.*.remarks' => ['nullable', 'string'],

            // Payments
            'payments' => ['nullable', 'array'],
            'payments.*.manifest_traveler_id' => ['nullable', 'integer', 'exists:manifest_members,id'],
            'payments.*.traveler_name' => ['nullable', 'string', 'max:255'],
            'payments.*.description' => ['nullable', 'string', 'max:500'],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.outstanding_amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.payment_date' => ['nullable', 'date'],
            'payments.*.status' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Validation rules for individual room CRUD operations.
     */
    public function roomRules(): array
    {
        return [
            'location' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'relationship' => ['nullable', 'string', 'max:100'],
            'room_number' => ['nullable', 'string', 'max:50'],
            'room_type' => ['nullable', 'string', 'in:single,twin,double,triple,quad'],
            'bed_type' => ['nullable', 'string', 'in:single,king,queen'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'sharing_plan' => ['nullable', 'string', 'in:single,double,triple,quad'],
            'status' => ['nullable', 'string', 'in:pending,filled,finalized'],
            'room_label' => ['nullable', 'string', 'max:255'],
            'meal' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'members' => ['nullable', 'array'],
            'members.*.manifest_traveler_id' => ['nullable', 'integer', 'exists:manifest_members,id'],
            'members.*.customer_confirmation_member_id' => ['nullable', 'integer', 'exists:customer_confirmation_members,id'],
            'members.*.sharing_plan' => ['nullable', 'string', 'in:single,double,triple,quad'],
            'members.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'members.*.remarks' => ['nullable', 'string'],
        ];
    }

    /**
     * Validation rules for individual payment CRUD operations.
     */
    public function paymentRules(): array
    {
        return [
            'manifest_traveler_id' => ['nullable', 'integer', 'exists:manifest_members,id'],
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
