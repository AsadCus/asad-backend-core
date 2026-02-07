import { z } from 'zod';

export const travelerSchema = z.object({
    id: z.number().optional(),
    sn: z.coerce.number().min(1, 'S/N is required.'),
    name_as_per_passport: z
        .string()
        .min(1, 'Name as per passport is required.'),
    relationship: z.string().optional().default(''),
    passport_no: z.string().optional().default(''),
    room_no: z.string().optional().default(''),
    room_type: z.string().optional().default(''),
    bed_type: z.string().optional().default(''),
    date_of_birth: z.string().optional().default(''),
    age: z.coerce.number().optional().default(0),
    no_of_beds_checked: z.coerce.number().optional().default(0),
    meal: z.string().optional().default(''),
    remarks: z.string().optional().default(''),
    total_cost: z.coerce.number().optional().default(0),
    total_paid: z.coerce.number().optional().default(0),
    outstanding_amount: z.coerce.number().optional().default(0),
});

export const manifestSchema = z.object({
    id: z.number().optional(),
    package_id: z.coerce.number().min(1, 'Package is required.'),
    reference_number: z.string().min(1, 'Reference number is required.'),
    company_address: z.string().optional().default(''),
    company_phone: z.string().optional().default(''),
    departure_date: z.string().min(1, 'Departure date is required.'),
    return_date: z.string().min(1, 'Return date is required.'),
    duration: z.string().optional().default(''),
    makkah_hotel: z.string().optional().default(''),
    makkah_check_in: z.string().optional().default(''),
    makkah_check_out: z.string().optional().default(''),
    madinah_hotel: z.string().optional().default(''),
    madinah_check_in: z.string().optional().default(''),
    madinah_check_out: z.string().optional().default(''),
    flight_details: z.any().optional().default(null),
    notes: z.string().optional().default(''),
    first_meal: z.string().optional().default(''),
    last_meal: z.string().optional().default(''),
    status: z.string().default('draft'),
    travelers: z.array(travelerSchema).optional().default([]),
});

export type ManifestSchema = z.infer<typeof manifestSchema>;
export type TravelerSchema = z.infer<typeof travelerSchema>;
