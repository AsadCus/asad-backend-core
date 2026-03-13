import { z } from 'zod';

export const travelerSchema = z.object({
    id: z.number().optional(),
    customer_id: z.number().nullable().optional(),
    customer_confirmation_member_id: z.number().nullable().optional(),
    package_official_id: z.number().nullable().optional(),
    is_official: z.boolean().nullable().optional(),
    is_assigned: z.boolean().nullable().optional(),
    customer_confirmation_id: z.number().nullable().optional(),
    customer_confirmation_number: z.string().nullable().optional(),
    manifest_sharing_group_id: z.number().nullable().optional(),
    customer_name: z.string().nullable().optional(),
    sn: z.coerce.number().nullable().optional(),
    name_as_per_passport: z.string().nullable().optional(),
    date_of_sign_up: z.string().nullable().optional(),
    package_category: z.string().nullable().optional(),
    is_first_time_umrah: z.boolean().nullable().optional(),
    passport_number: z.string().nullable().optional(),
    gender: z.string().nullable().optional(),
    date_of_birth: z.string().nullable().optional(),
    age: z.coerce.number().nullable().optional(),
    contact_no: z.string().nullable().optional(),
    date_of_issue: z.string().nullable().optional(),
    date_of_expiry: z.string().nullable().optional(),
    issue_place: z.string().nullable().optional(),
    birth_place: z.string().nullable().optional(),
    address: z.string().nullable().optional(),
    first_time_umrah: z.boolean().nullable().optional(),
    has_chronic_disease: z.boolean().nullable().optional(),
    chronic_disease_details: z.string().nullable().optional(),
    passport_path: z.string().nullable().optional(),
    photo_path: z.string().nullable().optional(),
    package_price: z.coerce.number().nullable().optional(),
    discount: z.coerce.number().nullable().optional(),
    date_of_deposit_payment: z.string().nullable().optional(),
    deposit_payment: z.coerce.number().nullable().optional(),
    date_of_second_payment: z.string().nullable().optional(),
    second_payment: z.coerce.number().nullable().optional(),
    balance_due: z.coerce.number().nullable().optional(),
    total_cost: z.coerce.number().nullable().optional(),
    total_paid: z.coerce.number().nullable().optional(),
    outstanding_amount: z.coerce.number().nullable().optional(),
    is_fully_paid: z.boolean().nullable().optional(),
    receipt_no: z.string().nullable().optional(),
    remarks: z.string().nullable().optional(),
    nationality: z.string().nullable().optional(),
    room_no: z.string().nullable().optional(),
    room_relationship: z.string().nullable().optional(),
    room_type: z.string().nullable().optional(),
    bed_type: z.string().nullable().optional(),
    number_of_beds_checked: z.boolean().nullable().optional(),
    no_of_beds_checked: z.coerce.number().nullable().optional(),
    meal: z.string().nullable().optional(),
    relationship: z.string().nullable().optional(),
    role: z.string().nullable().optional(),
    sharing_plan: z.string().nullable().optional(),
    group_remarks: z.string().nullable().optional(),
    status: z.string().nullable().optional(),
});

export const roomMemberSchema = z.object({
    id: z.number().optional(),
    manifest_traveler_id: z.number().optional(),
    traveler_name: z.string().optional(),
    role_in_room: z.string().optional(),
});

export const roomSchema = z.object({
    id: z.number().optional(),
    location: z.string().optional(),
    room_number: z.string().optional(),
    room_type: z.string().optional(),
    bed_type: z.string().optional(),
    capacity: z.coerce.number().optional(),
    sharing_plan: z.string().optional(),
    status: z.string().optional(),
    room_label: z.string().optional(),
    members: z.array(roomMemberSchema).optional(),
    sharing_groups: z
        .array(
            z.object({
                id: z.number().optional(),
                sharing_group_id: z.number().optional(),
                sharing_plan: z.string().optional(),
            }),
        )
        .optional(),
    // Legacy fields (used by existing UI rendering)
    customer_id: z.number().optional(),
    name_as_per_passport: z.string().optional(),
    relationship: z.string().optional(),
    passport_number: z.string().optional(),
    room_no: z.string().optional(),
    date_of_birth: z.string().optional(),
    age: z.coerce.number().optional(),
    no_of_beds_checked: z.coerce.number().optional(),
    meal: z.string().optional(),
    remarks: z.string().optional(),
});

export const paymentSchema = z.object({
    id: z.number().optional(),
    manifest_traveler_id: z.number().optional(),
    traveler_name: z.string().optional(),
    linked_traveler_name: z.string().optional(),
    description: z.string().optional(),
    amount: z.coerce.number().optional(),
    paid_amount: z.coerce.number().optional(),
    outstanding_amount: z.coerce.number().optional(),
    payment_date: z.string().optional(),
    status: z.string().optional(),
});

export const sharingGroupMemberSchema = z.object({
    id: z.number().optional(),
    customer_confirmation_member_id: z.number().optional(),
    role_in_group: z.string().optional(),
    sort_order: z.coerce.number().optional(),
    customer_name: z.string().optional(),
    customer_id: z.number().optional(),
});

export const manifestSharingGroupSchema = z.object({
    id: z.number().optional(),
    sharing_group_id: z.number().optional(),
    manifest_room_id: z.number().optional(),
    sharing_plan: z.string().optional(),
    expected_capacity: z.coerce.number().optional(),
    status: z.string().optional(),
    customer_confirmation_id: z.number().optional(),
    remarks: z.string().optional(),
    members: z.array(sharingGroupMemberSchema).optional(),
});

export const manifestSchema = z.object({
    id: z.number().optional(),
    members_count: z.coerce.number().nullable().optional(),
    package_id: z.coerce.number().optional(),
    manifest_number: z.string().optional(),
    notes: z.string().optional(),
    status: z.string().optional(),
    travelers: z.array(travelerSchema).optional(),
    rooms: z.array(roomSchema).optional(),
    payments: z.array(paymentSchema).optional(),
    sharing_groups: z.array(manifestSharingGroupSchema).optional(),
});

export type ManifestSchema = z.infer<typeof manifestSchema>;
export type TravelerSchema = z.infer<typeof travelerSchema>;
export type RoomSchema = z.infer<typeof roomSchema>;
export type RoomMemberSchema = z.infer<typeof roomMemberSchema>;
export type PaymentSchema = z.infer<typeof paymentSchema>;
export type SharingGroupMemberSchema = z.infer<typeof sharingGroupMemberSchema>;
export type ManifestSharingGroupSchema = z.infer<
    typeof manifestSharingGroupSchema
>;

// Nested structure types for API responses from ManifestService
export const hotelDetailsSchema = z.object({
    mekkah: z
        .object({
            hotel: z.string().optional(),
            checkIn: z.string().optional(),
            checkOut: z.string().optional(),
        })
        .optional(),
    madinah: z
        .object({
            hotel: z.string().optional(),
            checkIn: z.string().optional(),
            checkOut: z.string().optional(),
        })
        .optional(),
});

export const manifestInformationSchema = z.object({
    id: z.number().optional(),
    package_id: z.coerce.number().optional(),
    manifest_number: z.string().optional(),
    status: z.string().optional(),
    departure_date: z.string().optional(),
    return_date: z.string().optional(),
});

export const manifestApiResponseSchema = z.object({
    manifestInformation: manifestInformationSchema.optional(),
    hotelDetails: hotelDetailsSchema.optional(),
    travelers: z.record(z.string(), z.array(travelerSchema)).optional(),
    roomLists: z.record(z.string(), z.array(roomSchema)).optional(),
    airlinesNameList: z
        .record(
            z.string(),
            z.array(
                z.object({
                    sn: z.number().optional(),
                    nameAsPerPassport: z.string().optional(),
                    nationality: z.string().optional(),
                    passportNo: z.string().optional(),
                    gender: z.string().optional(),
                    dateOfBirth: z.string().optional(),
                    dateOfIssue: z.string().optional(),
                    dateOfExpiry: z.string().optional(),
                    issuePlace: z.string().optional(),
                    remarks: z.string().optional(),
                }),
            ),
        )
        .optional(),
});

export type HotelDetailsSchema = z.infer<typeof hotelDetailsSchema>;
export type ManifestInformationSchema = z.infer<
    typeof manifestInformationSchema
>;
export type ManifestApiResponseSchema = z.infer<
    typeof manifestApiResponseSchema
>;

export const manifestStatusOptions = [
    { label: 'Draft', value: 'draft' },
    { label: 'Confirmed', value: 'confirmed' },
    { label: 'Completed', value: 'completed' },
    { label: 'Cancelled', value: 'cancelled' },
];

export const manifestStatusColors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-800',
    confirmed: 'bg-blue-100 text-blue-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
};

export const manifestStatusLabels: Record<string, string> = {
    draft: 'Draft',
    confirmed: 'Confirmed',
    completed: 'Completed',
    cancelled: 'Cancelled',
};

export const roomStatusOptions = [
    { label: 'Pending', value: 'pending' },
    { label: 'Filled', value: 'filled' },
    { label: 'Finalized', value: 'finalized' },
];
