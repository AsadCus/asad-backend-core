import { z } from 'zod';

export const travelerSchema = z.object({
    id: z.number().optional(),
    sn: z.coerce.number().optional(),
    name_as_per_passport: z.string().optional(),
    relationship: z.string().optional(),
    passport_no: z.string().optional(),
    room_no: z.string().optional(),
    room_type: z.string().optional(),
    bed_type: z.string().optional(),
    date_of_birth: z.string().optional(),
    age: z.coerce.number().optional(),
    no_of_beds_checked: z.coerce.number().optional(),
    meal: z.string().optional(),
    remarks: z.string().optional(),
    total_cost: z.coerce.number().optional(),
    total_paid: z.coerce.number().optional(),
    outstanding_amount: z.coerce.number().optional(),
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
});

export type ManifestSchema = z.infer<typeof manifestSchema>;
export type TravelerSchema = z.infer<typeof travelerSchema>;

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
