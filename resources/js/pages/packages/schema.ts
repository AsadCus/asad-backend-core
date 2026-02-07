import { z } from 'zod';

export const accommodationSchema = z.object({
    id: z.number().optional(),
    location: z.string().min(1, 'Location is required'),
    hotel_name: z.string().min(1, 'Hotel name is required'),
    type_of_meal: z.string().nullable().optional(),
    check_in: z.string().nullable().optional(),
    check_out: z.string().nullable().optional(),
});

export const packageSchema = z.object({
    id: z.number().optional(),
    group_number: z.string().optional(),
    name: z
        .string()
        .min(1, 'Package name is required')
        .min(2, 'Package name must be at least 2 characters'),
    status: z.enum(['open', 'closed']),

    // Pricing
    price_single: z.number().min(0, 'Price cannot be negative'),
    price_double: z.number().min(0, 'Price cannot be negative'),
    price_triple: z.number().min(0, 'Price cannot be negative'),
    price_quad: z.number().min(0, 'Price cannot be negative'),
    child_with_bed_price: z.number().min(0, 'Price cannot be negative'),
    child_no_bed_price: z.number().min(0, 'Price cannot be negative'),
    infant_price: z.number().min(0, 'Price cannot be negative'),

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
