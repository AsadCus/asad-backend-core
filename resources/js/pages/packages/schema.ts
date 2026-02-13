import { z } from 'zod';

export const accommodationSchema = z.object({
    id: z.number().optional(),
    location: z.string().optional(),
    hotel_name: z.string().optional(),
    type_of_meal: z.string().nullable().optional(),
    check_in: z.string().nullable().optional(),
    check_out: z.string().nullable().optional(),
});

export const packageSchema = z.object({
    id: z.number().optional(),
    group_number: z.string().optional(),
    name: z.string().optional(),
    status: z.enum(['open', 'closed']).optional(),

    // Pricing
    price_single: z.number().optional(),
    price_double: z.number().optional(),
    price_triple: z.number().optional(),
    price_quad: z.number().optional(),
    child_with_bed_price: z.number().optional(),
    child_no_bed_price: z.number().optional(),
    infant_price: z.number().optional(),

    // Flight Details
    airline: z.string().nullable().optional(),
    pnr: z.string().nullable().optional(),
    departure_date: z.string().nullable().optional(),
    arrival_date: z.string().nullable().optional(),
    total_seats: z.number().nullable().optional(),
    seats_left: z.number().nullable().optional(),

    // Visa
    visa_type: z.string().nullable().optional(),

    // Vehicle
    vehicle_type: z.string().nullable().optional(),

    // Train
    ticket_type: z.string().nullable().optional(),

    // Inclusions
    included: z.string().nullable().optional(),
    not_included: z.string().nullable().optional(),

    // Remarks
    remarks: z.string().nullable().optional(),

    // Accommodations
    accommodations: z.array(accommodationSchema).optional(),
});

export type PackageSchema = z.infer<typeof packageSchema>;
export type AccommodationSchema = z.infer<typeof accommodationSchema>;

export const packageStatusOptions = [
    { label: 'Open', value: 'open' },
    { label: 'Closed', value: 'closed' },
];

export const packageStatusColors = {
    open: 'bg-green-100 text-green-800',
    closed: 'bg-red-100 text-red-800',
};

export const packageStatusLabels = {
    open: 'Open',
    closed: 'Closed',
};
