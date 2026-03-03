import { z } from 'zod';

export const travelerSchema = z.object({
    id: z.number().optional(),
    customer_id: z.number().optional(),
    customer_confirmation_member_id: z.number().optional(),
    customer_name: z.string().optional(),
    sn: z.coerce.number().optional(),
    name_as_per_passport: z.string().optional(),
    date_of_sign_up: z.string().optional(),
    is_first_time_umrah: z.boolean().optional(),
    ppt_no: z.string().optional(),
    passport_no: z.string().optional(),
    gender: z.string().optional(),
    date_of_birth: z.string().optional(),
    age: z.coerce.number().optional(),
    contact_no: z.string().optional(),
    date_of_issue: z.string().optional(),
    date_of_expiry: z.string().optional(),
    issue_place: z.string().optional(),
    birth_place: z.string().optional(),
    package_price: z.coerce.number().optional(),
    discount: z.coerce.number().optional(),
    date_of_deposit_payment: z.string().optional(),
    deposit_payment: z.coerce.number().optional(),
    date_of_second_payment: z.string().optional(),
    second_payment: z.coerce.number().optional(),
    balance_due: z.coerce.number().optional(),
    total_cost: z.coerce.number().optional(),
    total_paid: z.coerce.number().optional(),
    outstanding_amount: z.coerce.number().optional(),
    is_fully_paid: z.boolean().optional(),
    receipt_no: z.string().optional(),
    remarks: z.string().optional(),
    nationality: z.string().optional(),
    room_no: z.string().optional(),
    room_type: z.string().optional(),
    bed_type: z.string().optional(),
    no_of_beds_checked: z.coerce.number().optional(),
    meal: z.string().optional(),
    relationship: z.string().optional(),
    status: z.string().optional(),
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
    passport_no: z.string().optional(),
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
    package_id: z.coerce.number().optional(),
    reference_number: z.string().optional(),
    company_address: z.string().optional(),
    company_phone: z.string().optional(),
    departure_date: z.string().optional(),
    return_date: z.string().optional(),
    duration: z.string().optional(),
    makkah_hotel: z.string().optional(),
    makkah_check_in: z.string().optional(),
    makkah_check_out: z.string().optional(),
    madinah_hotel: z.string().optional(),
    madinah_check_in: z.string().optional(),
    madinah_check_out: z.string().optional(),
    flight_details: z.any().optional(),
    notes: z.string().optional(),
    first_meal: z.string().optional(),
    last_meal: z.string().optional(),
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
    makkah: z
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

export const mealsNotesSchema = z.object({
    firstMeal: z.string().optional(),
    lastMeal: z.string().optional(),
    notes: z.string().optional(),
});

export const manifestInformationSchema = z.object({
    id: z.number().optional(),
    package_id: z.coerce.number().optional(),
    reference_number: z.string().optional(),
    status: z.string().optional(),
    company_address: z.string().optional(),
    company_phone: z.string().optional(),
    departure_date: z.string().optional(),
    return_date: z.string().optional(),
    duration: z.string().optional(),
});

export const manifestApiResponseSchema = z.object({
    manifestInformation: manifestInformationSchema.optional(),
    hotelDetails: hotelDetailsSchema.optional(),
    mealsNotes: mealsNotesSchema.optional(),
    travelers: z.record(z.string(), z.array(travelerSchema)).optional(),
    roomListMakkah: z.record(z.string(), z.array(roomSchema)).optional(),
    roomListMadinah: z.record(z.string(), z.array(roomSchema)).optional(),
    roomListOthers: z.record(z.string(), z.array(roomSchema)).optional(),
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
export type MealsNotesSchema = z.infer<typeof mealsNotesSchema>;
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

export const manifestStatusColors = {
    draft: 'bg-gray-100 text-gray-800',
    confirmed: 'bg-blue-100 text-blue-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
};

export const manifestStatusLabels = {
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
